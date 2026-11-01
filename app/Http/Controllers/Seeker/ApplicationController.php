<?php

namespace App\Http\Controllers\Seeker;

use App\Enums\ApplicationStatus;
use App\Enums\OpportunityCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seeker\SubmitApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusHistory;
use App\Models\Opportunity;
use App\Models\OpportunityDocument;
use App\Services\Applications\ApplicationWorkflow;
use App\Services\Profiles\ProfileCompletion;
use App\Support\OpportunityCard;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    /**
     * Tabs of the "My applications" list and the statuses each one shows.
     */
    private const TABS = [
        'active' => [
            ApplicationStatus::Submitted, ApplicationStatus::UnderReview, ApplicationStatus::Shortlisted,
            ApplicationStatus::ForInterview, ApplicationStatus::ForFurtherRequirements,
        ],
        'approved' => [ApplicationStatus::Approved],
        'rejected' => [ApplicationStatus::Rejected],
        'withdrawn' => [ApplicationStatus::Withdrawn],
    ];

    public function index(Request $request): Response
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'all';
        $applications = $request->user()->applications();

        $counts = (clone $applications)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $page = $applications
            ->when($tab !== 'all', fn ($q) => $q->whereIn('status', self::TABS[$tab]))
            ->with($this->opportunityRelations())
            ->latest('submitted_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('seeker/applications/index', [
            'applications' => $page->through(fn (Application $application) => [
                ...$this->summary($application),
                'opportunity' => OpportunityCard::make($application->opportunity),
            ]),
            'tab' => $tab,
            'counts' => [
                'all' => (int) $counts->sum(),
                ...collect(self::TABS)->map(fn (array $statuses) => (int) collect($statuses)->sum(fn (ApplicationStatus $s) => $counts[$s->value] ?? 0))->all(),
            ],
        ]);
    }

    public function create(Request $request, Opportunity $opportunity, ProfileCompletion $completion): Response|RedirectResponse
    {
        abort_unless(Opportunity::query()->visible()->whereKey($opportunity->id)->exists(), 404);

        $user = $request->user();

        if ($existing = $user->applications()->withTrashed()->where('opportunity_id', $opportunity->id)->first()) {
            return to_route('seeker.applications.show', $existing)->with('success', 'You have already applied to this opportunity.');
        }

        if (! $opportunity->isAcceptingApplications()) {
            return to_route('opportunities.show', $opportunity);
        }

        $opportunity->load(['organization', 'barangay', 'jobDetail', 'requiredDocuments']);
        $resume = $user->currentResume;

        return Inertia::render('seeker/applications/create', [
            'opportunity' => OpportunityCard::make($opportunity),
            'instructions' => $opportunity->application_instructions,
            'documents' => $opportunity->requiredDocuments->map(fn (OpportunityDocument $document) => [
                'id' => $document->id,
                'name' => $document->name,
                'is_required' => $document->is_required,
                'is_resume' => $document->isResume(),
                'accept' => collect(explode(',', SubmitApplicationRequest::fileTypes($document)))->map(fn ($type) => '.'.$type)->implode(','),
                'types_label' => strtoupper(str_replace(',jpeg', '', SubmitApplicationRequest::fileTypes($document))),
            ]),
            'resume' => $resume ? [
                'id' => $resume->id,
                'original_name' => $resume->original_name,
                'uploaded_at' => $resume->created_at?->toIso8601String(),
                'url' => route('seeker.resume.file', ['resume' => $resume, 'preview' => 1]),
            ] : null,
            'resumeRequired' => $opportunity->category === OpportunityCategory::Job,
            'completion' => $completion->for($user)['percent'],
        ]);
    }

    public function store(SubmitApplicationRequest $request, Opportunity $opportunity, ApplicationWorkflow $workflow): RedirectResponse
    {
        abort_unless(Opportunity::query()->visible()->whereKey($opportunity->id)->exists(), 404);

        $application = $workflow->submit(
            $request->user(),
            $opportunity,
            [
                'cover_letter' => $request->validated('cover_letter'),
                'include_resume' => $request->boolean('include_resume', true),
            ],
            $request->documentFiles(),
        );

        return to_route('seeker.applications.show', $application)
            ->with('success', "Application submitted to {$opportunity->organization->name}. You can track its status here.");
    }

    public function show(Application $application): Response
    {
        Gate::authorize('view', $application);

        $application->load([...$this->opportunityRelations(), 'documents', 'resume', 'statusHistory']);

        return Inertia::render('seeker/applications/show', [
            'application' => [
                ...$this->summary($application),
                'status_description' => $application->status->description(),
                'cover_letter' => $application->cover_letter,
                'withdrawal_reason' => $application->withdrawal_reason,
                'can_withdraw' => $application->canBeWithdrawn(),
                'accepts_documents' => $application->acceptsAdditionalDocuments(),
                'resume' => $application->resume ? [
                    'original_name' => $application->resume->original_name,
                    'url' => route('seeker.resume.file', $application->resume),
                    'preview_url' => $application->resume->isPdf() ? route('seeker.resume.file', ['resume' => $application->resume, 'preview' => 1]) : null,
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
                'history' => $application->statusHistory->map(fn (ApplicationStatusHistory $entry) => [
                    'status' => $entry->to_status->value,
                    'label' => $entry->to_status->label(),
                    'remarks' => $entry->remarks,
                    'by_applicant' => (int) $entry->changed_by === (int) $application->user_id,
                    'at' => $entry->created_at->toIso8601String(),
                ]),
            ],
            'opportunity' => OpportunityCard::make($application->opportunity),
            'opportunityAvailable' => Opportunity::query()->visible()->whereKey($application->opportunity_id)->exists(),
        ]);
    }

    public function withdraw(Request $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('withdraw', $application);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $workflow->withdraw($application, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Your application was withdrawn.');
    }

    /**
     * The opportunity (even if later removed) with what its card needs.
     *
     * @return array<string, \Closure(Relation<*, *, *>): mixed>
     */
    private function opportunityRelations(): array
    {
        return [
            'opportunity' => fn (Relation $q) => $q->withTrashed()->with([
                'organization' => fn (Relation $o) => $o->withTrashed(),
                'barangay',
                'jobDetail',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Application $application): array
    {
        return [
            'id' => $application->id,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'submitted_at' => $application->submitted_at->toIso8601String(),
            'updated_at' => $application->updated_at?->toIso8601String(),
        ];
    }
}
