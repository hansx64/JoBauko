<?php

namespace App\Http\Controllers\Organization;

use App\Enums\ApplicationStatus;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organization\Concerns\ResolvesOrganization;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationRemark;
use App\Models\ApplicationStatusHistory;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Services\Audit\AuditLogger;
use App\Services\Recommendation\MatchScorer;
use App\Services\Recommendation\SeekerSnapshot;
use App\Services\Resumes\ResumeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Applicants of the signed-in member's organization. Every query is scoped
 * to the organization's opportunities; single applications are re-checked
 * by ApplicationPolicy.
 */
class ApplicationController extends Controller
{
    use ResolvesOrganization;

    private const SORTS = ['newest', 'oldest', 'match'];

    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $opportunityIds = $this->opportunityIds($organization);

        $filters = [
            'opportunity' => in_array($request->integer('opportunity'), $opportunityIds, true) ? $request->integer('opportunity') : null,
            'status' => $request->query('status') === 'active' ? 'active' : ApplicationStatus::tryFrom((string) $request->query('status'))?->value,
            'q' => trim((string) $request->query('q')),
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'newest',
        ];

        $scope = Application::query()
            ->whereIn('opportunity_id', $filters['opportunity'] ? [$filters['opportunity']] : $opportunityIds);

        $counts = (clone $scope)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $query = (clone $scope)
            ->when($filters['status'] === 'active', fn (Builder $q) => $q->whereIn('status', ApplicationStatus::active()))
            ->when($filters['status'] && $filters['status'] !== 'active', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
                $q->whereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->with([
                'user' => fn ($q) => $q->withTrashed()->with(['barangay', 'applicantProfile']),
                'opportunity' => fn ($q) => $q->withTrashed(),
            ])
            ->withCount('documents');

        match ($filters['sort']) {
            'oldest' => $query->oldest('submitted_at'),
            'match' => $query->orderByRaw('match_score is null')->orderByDesc('match_score'),
            default => $query->latest('submitted_at'),
        };

        $page = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('organization/applications/index', [
            'organization' => $this->organizationSummary($organization),
            'applications' => $page->through(fn (Application $application) => [
                'id' => $application->id,
                'applicant' => [
                    'name' => $application->user->name,
                    'email' => $application->user->email,
                    'barangay' => $application->user->barangay?->name,
                    'classification' => $application->user->applicantProfile?->classification->label(),
                ],
                'opportunity' => [
                    'id' => $application->opportunity->id,
                    'title' => $application->opportunity->title,
                    'category_label' => $application->opportunity->category->label(),
                ],
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'match_score' => $application->match_score,
                'documents_count' => $application->documents_count,
                'submitted_at' => $application->submitted_at->toIso8601String(),
            ]),
            'filters' => $filters,
            'counts' => [
                'all' => (int) $counts->sum(),
                'active' => (int) collect(ApplicationStatus::active())->sum(fn (ApplicationStatus $s) => $counts[$s->value] ?? 0),
                ...collect(ApplicationStatus::cases())->mapWithKeys(fn (ApplicationStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all(),
            ],
            'options' => [
                'opportunities' => $organization->opportunities()->withTrashed()
                    ->whereHas('applications')
                    ->orderBy('title')
                    ->get(['id', 'title']),
                'statuses' => ApplicationStatus::options(),
            ],
        ]);
    }

    public function show(Request $request, Application $application, MatchScorer $scorer, AuditLogger $audit): Response
    {
        Gate::authorize('view', $application);

        $application->load([
            'user' => fn ($q) => $q->withTrashed()->with([
                'barangay',
                'applicantProfile.education',
                'applicantProfile.experience',
                'applicantProfile.certifications',
                'applicantProfile.trainings',
                'applicantProfile.skills',
            ]),
            'opportunity' => fn ($q) => $q->withTrashed()->with(['organization', 'requiredDocuments', 'skills', 'jobDetail', 'scholarshipDetail', 'livelihoodDetail', 'trainingDetail']),
            'resume',
            'documents',
            'statusHistory.changer',
            'remarks.author',
        ]);

        // Access to an applicant's personal data is logged (Data Privacy Act).
        $audit->log('application.viewed', $application, organizationId: $application->opportunity->organization_id);

        $user = $application->user;
        $profile = $user->applicantProfile;
        $membership = $request->user()->organizationMembership;

        return Inertia::render('organization/applications/show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'is_final' => $application->status->isFinal(),
                'submitted_at' => $application->submitted_at->toIso8601String(),
                'cover_letter' => $application->cover_letter,
                'withdrawal_reason' => $application->withdrawal_reason,
                'match_score_at_submission' => $application->match_score,
                'resume' => $application->resume ? [
                    'original_name' => $application->resume->original_name,
                    'url' => route('org.applications.resume', $application),
                    'preview_url' => $application->resume->isPdf() ? route('org.applications.resume', ['application' => $application, 'preview' => 1]) : null,
                ] : null,
                'documents' => $application->documents->map(fn (ApplicationDocument $document) => [
                    'id' => $document->id,
                    'label' => $document->label,
                    'original_name' => $document->original_name,
                    'size_bytes' => $document->size_bytes,
                    'uploaded_at' => $document->created_at?->toIso8601String(),
                    'added_later' => $document->opportunity_document_id === null,
                    'url' => route('application-documents.show', $document),
                    'preview_url' => $document->isPreviewable() ? route('application-documents.show', ['document' => $document, 'preview' => 1]) : null,
                ]),
                'missing_documents' => $application->opportunity->requiredDocuments
                    ->where('is_required', true)
                    ->whereNotIn('id', $application->documents->pluck('opportunity_document_id')->filter())
                    ->pluck('name')
                    ->values(),
                'history' => $application->statusHistory->map(fn (ApplicationStatusHistory $entry) => [
                    'status' => $entry->to_status->value,
                    'label' => $entry->to_status->label(),
                    'remarks' => $entry->remarks,
                    'by' => (int) $entry->changed_by === (int) $application->user_id ? 'Applicant' : ($entry->changer?->name ?? 'Former member'),
                    'at' => $entry->created_at->toIso8601String(),
                ]),
                'remarks' => $application->remarks->map(fn (ApplicationRemark $remark) => [
                    'id' => $remark->id,
                    'body' => $remark->body,
                    'author' => $remark->author?->name ?? 'Former member',
                    'at' => $remark->created_at?->toIso8601String(),
                ]),
            ],
            'opportunity' => [
                'id' => $application->opportunity->id,
                'slug' => $application->opportunity->slug,
                'title' => $application->opportunity->title,
                'category_label' => $application->opportunity->category->label(),
                'deleted' => $application->opportunity->trashed(),
            ],
            'applicant' => [
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'barangay' => $user->barangay?->name,
                'classification' => $profile?->classification->label(),
                'gender' => $profile?->gender?->label(),
                'age' => $profile?->date_of_birth?->age,
                'address' => $profile?->address_line,
                'highest_education' => $profile?->highest_education?->label(),
                'summary' => $profile?->summary,
                'education' => $profile?->education->map(fn ($e) => [
                    'id' => $e->id,
                    'level' => $e->level?->label(),
                    'school' => $e->school,
                    'course' => $e->course,
                    'year_graduated' => $e->year_graduated,
                    'is_current' => $e->is_current,
                ]) ?? [],
                'experience' => $profile?->experience->map(fn ($e) => [
                    'id' => $e->id,
                    'position' => $e->position,
                    'company' => $e->company,
                    'start_date' => $e->start_date?->toDateString(),
                    'end_date' => $e->end_date?->toDateString(),
                    'description' => $e->description,
                ]) ?? [],
                'certifications' => $profile?->certifications->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'issuer' => $c->issuer,
                    'issued_at' => $c->issued_at?->toDateString(),
                ]) ?? [],
                'trainings' => $profile?->trainings->map(fn ($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'provider' => $t->provider,
                    'completed_at' => $t->completed_at?->toDateString(),
                    'hours' => $t->hours,
                ]) ?? [],
                'skills' => $profile?->skills->pluck('name') ?? [],
            ],
            'match' => $scorer->score(SeekerSnapshot::fromUser($user), $application->opportunity)->toArray(),
            'nextStatuses' => collect($application->status->nextStatuses())
                ->filter(fn (ApplicationStatus $status) => Gate::allows('changeStatus', [$application, $status]))
                ->map(fn (ApplicationStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->values(),
            'canRemark' => (bool) $membership?->hasPermission(OrganizationPermission::AddRemarks),
        ]);
    }

    /**
     * Stream the resume the applicant submitted with this application.
     */
    public function resume(Request $request, Application $application): StreamedResponse
    {
        Gate::authorize('view', $application);

        $resume = $application->resume;
        abort_if($resume === null, 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(ResumeService::DISK);

        return $disk->response(
            $resume->disk_path,
            $resume->original_name,
            [
                'Content-Type' => $resume->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $request->boolean('preview') && $resume->isPdf() ? 'inline' : 'attachment',
        );
    }

    /**
     * @return list<int>
     */
    private function opportunityIds(Organization $organization): array
    {
        return Opportunity::withTrashed()->where('organization_id', $organization->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
