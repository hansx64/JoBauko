<?php

namespace App\Services\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\OpportunityCategory;
use App\Enums\OrganizationPermission;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationRemark;
use App\Models\Opportunity;
use App\Models\OpportunityDocument;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\ApplicationActivity;
use App\Notifications\ApplicationStatusUpdated;
use App\Services\Audit\AuditLogger;
use App\Services\Profiles\ProfileCompletion;
use App\Services\Recommendation\MatchScorer;
use App\Services\Recommendation\SeekerSnapshot;
use App\Support\UploadPaths;
use App\Support\Uploads;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Submitting, withdrawing and moving applications between statuses. Every
 * change is written to the status history and the audit log, and the other
 * side (applicant or organization) is notified.
 */
class ApplicationWorkflow
{
    public const MAX_DOCUMENTS = 20;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MatchScorer $scorer,
        private readonly ProfileCompletion $completion,
    ) {}

    /**
     * @param  array{cover_letter?: string|null, include_resume?: bool}  $data
     * @param  array<int, UploadedFile>  $files  keyed by opportunity document id
     */
    public function submit(User $user, Opportunity $opportunity, array $data, array $files): Application
    {
        $this->ensureAcceptingApplications($opportunity);

        if (Application::withTrashed()->where('opportunity_id', $opportunity->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['application' => 'You have already applied to this opportunity.']);
        }

        if ($missing = $this->completion->missingToApply($user, $opportunity)) {
            $labels = array_map(fn (array $section) => Str::lower($section['label']), $missing);

            throw ValidationException::withMessages(['application' => 'Finish your profile before applying. Still missing: '.implode(', ', $labels).'.']);
        }

        $resume = ($data['include_resume'] ?? true) ? $user->currentResume : null;

        if ($resume === null && $opportunity->category === OpportunityCategory::Job) {
            throw ValidationException::withMessages(['resume' => 'A resume is required for job applications. Upload one on your profile, or create one with the resume builder.']);
        }

        $requirements = $opportunity->requiredDocuments()->get()->keyBy('id');
        $missing = $requirements->filter(fn (OpportunityDocument $requirement) => $requirement->is_required
            && ! isset($files[$requirement->id])
            && ! ($resume !== null && $requirement->isResume()));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages($missing->mapWithKeys(
                fn ($requirement) => ["documents.{$requirement->id}" => "Attach your {$requirement->name}."],
            )->all());
        }

        $stored = [];

        try {
            $application = DB::transaction(function () use ($user, $opportunity, $data, $files, $resume, $requirements, &$stored) {
                $application = new Application(['cover_letter' => $data['cover_letter'] ?? null]);
                $application->opportunity_id = $opportunity->id;
                $application->user_id = $user->id;
                $application->resume_id = $resume?->id;
                $application->status = ApplicationStatus::Submitted;
                $application->match_score = $this->scorer->score(SeekerSnapshot::fromUser($user), $opportunity)->score;
                $application->data_sharing_consented_at = now();
                $application->submitted_at = now();
                $application->save();

                foreach ($files as $requirementId => $file) {
                    if ($requirement = $requirements->get($requirementId)) {
                        $stored[] = $this->storeDocument($application, $user, $file, $requirement->name, $requirement->id)->disk_path;
                    }
                }

                $this->recordStatus($application, null, ApplicationStatus::Submitted, $user);
                $this->audit->log('application.submitted', $application, new: ['opportunity_id' => $opportunity->id], organizationId: $opportunity->organization_id);

                return $application;
            });
        } catch (UniqueConstraintViolationException) {
            // A second submission raced past the check above.
            Uploads::disk()->delete($stored);

            throw ValidationException::withMessages(['application' => 'You have already applied to this opportunity.']);
        } catch (Throwable $e) {
            Uploads::disk()->delete($stored);

            throw $e;
        }

        $this->notifyOrganization($application, 'submitted');

        return $application;
    }

    /**
     * Move an application to another status on behalf of the organization.
     * Permission to do so is checked by ApplicationPolicy.
     */
    public function transition(Application $application, ApplicationStatus $to, User $actor, ?string $remarks = null): Application
    {
        $from = $application->status;
        $remarks = $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null;

        if (! $from->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => "An application that is {$from->label()} cannot be changed to {$to->label()}."]);
        }

        if ($to === ApplicationStatus::ForFurtherRequirements && $remarks === null) {
            throw ValidationException::withMessages(['remarks' => 'Tell the applicant which documents or information are needed.']);
        }

        DB::transaction(function () use ($application, $from, $to, $actor, $remarks) {
            $application->status = $to;
            $application->first_reviewed_at ??= now();

            if ($to->isDecision()) {
                $application->decided_at = now();
            }

            $application->save();

            $this->recordStatus($application, $from, $to, $actor, $remarks);
            $this->audit->log(
                'application.status_changed',
                $application,
                ['status' => $from->value],
                ['status' => $to->value, 'remarks' => $remarks],
                $this->organizationId($application),
            );
        });

        $application->user->notify(new ApplicationStatusUpdated($application, $remarks));

        return $application;
    }

    public function withdraw(Application $application, User $user, ?string $reason = null): Application
    {
        if (! $application->canBeWithdrawn()) {
            throw ValidationException::withMessages(['application' => "An application that is {$application->status->label()} can no longer be withdrawn."]);
        }

        $from = $application->status;
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        DB::transaction(function () use ($application, $user, $from, $reason) {
            $application->status = ApplicationStatus::Withdrawn;
            $application->withdrawn_at = now();
            $application->withdrawal_reason = $reason;
            $application->save();

            $this->recordStatus($application, $from, ApplicationStatus::Withdrawn, $user, $reason);
            $this->audit->log('application.withdrawn', $application, ['status' => $from->value], ['status' => ApplicationStatus::Withdrawn->value], $this->organizationId($application));
        });

        $this->notifyOrganization($application, 'withdrawn');

        return $application;
    }

    /**
     * Add a document the organization asked for while the application is
     * "For Further Requirements".
     */
    public function addDocument(Application $application, User $user, string $label, UploadedFile $file): ApplicationDocument
    {
        if (! $application->acceptsAdditionalDocuments()) {
            throw ValidationException::withMessages(['file' => 'Documents can only be added when the organization asks for further requirements.']);
        }

        if ($application->documents()->count() >= self::MAX_DOCUMENTS) {
            throw ValidationException::withMessages(['file' => 'This application already has the maximum of '.self::MAX_DOCUMENTS.' documents.']);
        }

        $document = $this->storeDocument($application, $user, $file, $label);

        $this->audit->log('application.document_added', $application, new: ['document_id' => $document->id, 'label' => $label], organizationId: $this->organizationId($application));
        $this->notifyOrganization($application, 'documents_added');

        return $document;
    }

    /**
     * Add an internal note of the organization. Never shown to the applicant.
     */
    public function addRemark(Application $application, User $author, string $body): ApplicationRemark
    {
        $remark = $application->remarks()->create(['user_id' => $author->id, 'body' => trim($body)]);

        $this->audit->log('application.remark_added', $application, new: ['remark_id' => $remark->id], organizationId: $this->organizationId($application));

        return $remark;
    }

    private function ensureAcceptingApplications(Opportunity $opportunity): void
    {
        $visible = Opportunity::query()->visible()->whereKey($opportunity->id)->exists();

        if (! $visible || ! $opportunity->isAcceptingApplications()) {
            throw ValidationException::withMessages(['application' => 'This opportunity is not accepting applications.']);
        }
    }

    private function storeDocument(Application $application, User $user, UploadedFile $file, string $label, ?int $requirementId = null): ApplicationDocument
    {
        $extension = $file->guessExtension() ?: Str::lower($file->getClientOriginalExtension());
        $path = $file->storeAs(UploadPaths::application($application), UploadPaths::fileName($label, $extension), Uploads::name());

        try {
            return $application->documents()->create([
                'opportunity_document_id' => $requirementId,
                'label' => Str::limit($label, 150, ''),
                'disk_path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);
        } catch (Throwable $e) {
            Uploads::disk()->delete($path);

            throw $e;
        }
    }

    private function recordStatus(Application $application, ?ApplicationStatus $from, ApplicationStatus $to, User $actor, ?string $remarks = null): void
    {
        $application->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor->id,
            'remarks' => $remarks,
        ]);
    }

    /**
     * @param  'submitted'|'withdrawn'|'documents_added'  $event
     */
    private function notifyOrganization(Application $application, string $event): void
    {
        $recipients = $this->reviewers($application);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ApplicationActivity($application, $event));
        }
    }

    /**
     * Active personnel of the opportunity's organization who may see applications.
     *
     * @return Collection<int, User>
     */
    private function reviewers(Application $application): Collection
    {
        /** @var Collection<int, OrganizationMember> $members */
        $members = OrganizationMember::query()
            ->where('organization_id', $this->organizationId($application))
            ->where('is_active', true)
            ->with('user')
            ->get();

        return $members
            ->filter(fn (OrganizationMember $member) => $member->hasPermission(OrganizationPermission::ViewApplications) && $member->user !== null)
            ->map(fn (OrganizationMember $member) => $member->user)
            ->values();
    }

    private function organizationId(Application $application): int
    {
        return (int) $application->opportunity()->withTrashed()->value('organization_id');
    }
}
