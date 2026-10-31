<?php

namespace App\Http\Controllers\Organization;

use App\Enums\AssistanceType;
use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Enums\WorkSetup;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organization\Concerns\ResolvesOrganization;
use App\Http\Requests\Organization\OpportunityRequest;
use App\Models\Barangay;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Services\Opportunities\OpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    use ResolvesOrganization;

    private const FILTERS = ['all', 'draft', 'published', 'expired', 'closed', 'archived', 'suspended'];

    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : 'all';
        $search = trim((string) $request->query('search'));

        $query = $organization->opportunities()->withCount('applications')->latest('updated_at');

        match ($filter) {
            'all' => null,
            'published' => $query->where('status', OpportunityStatus::Published)->whereDate('application_deadline', '>=', today()),
            'expired' => $query->where('status', OpportunityStatus::Published)->whereDate('application_deadline', '<', today()),
            default => $query->where('status', $filter),
        };

        if ($search !== '') {
            $query->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%');
        }

        return Inertia::render('organization/opportunities/index', [
            'organization' => $this->organizationSummary($organization),
            'opportunities' => $query->paginate(15)->withQueryString()->through(fn (Opportunity $o) => [
                'id' => $o->id,
                'title' => $o->title,
                'category' => $o->category->value,
                'category_label' => $o->category->label(),
                'status' => $o->displayStatus(),
                'status_label' => $o->displayStatusLabel(),
                'deadline' => $o->application_deadline?->toDateString(),
                'slots' => $o->slots,
                'applications_count' => (int) $o->getAttribute('applications_count'),
                'updated_at' => $o->updated_at->toIso8601String(),
            ]),
            'filters' => ['status' => $filter, 'search' => $search],
            'canCreate' => Gate::allows('create', Opportunity::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Opportunity::class);

        return Inertia::render('organization/opportunities/form', [
            ...$this->formOptions($request),
            'opportunity' => null,
            'publishProblems' => [],
        ]);
    }

    public function store(OpportunityRequest $request, OpportunityService $opportunities): RedirectResponse
    {
        Gate::authorize('create', Opportunity::class);

        $opportunity = $opportunities->create($this->organization($request), $request->user(), $request->validated());

        if (! $request->isPublishing()) {
            return to_route('org.opportunities.edit', $opportunity)->with('success', 'Draft saved.');
        }

        try {
            $opportunities->publish($opportunity->refresh());
        } catch (ValidationException $e) {
            // Keep the saved draft and show why it couldn't go live.
            return to_route('org.opportunities.edit', $opportunity)->withErrors($e->errors());
        }

        return to_route('org.opportunities.index')->with('success', "“{$opportunity->title}” is now published.");
    }

    public function edit(Request $request, Opportunity $opportunity, OpportunityService $opportunities): Response
    {
        Gate::authorize('manage', $opportunity);

        return Inertia::render('organization/opportunities/form', [
            ...$this->formOptions($request),
            'opportunity' => $this->formData($opportunity),
            'publishProblems' => $opportunity->status === OpportunityStatus::Draft ? $opportunities->publishProblems($opportunity) : [],
        ]);
    }

    public function update(OpportunityRequest $request, Opportunity $opportunity, OpportunityService $opportunities): RedirectResponse
    {
        Gate::authorize('manage', $opportunity);

        $opportunities->update($opportunity, $request->validated());

        if ($request->isPublishing() && $opportunity->status === OpportunityStatus::Draft) {
            $opportunities->publish($opportunity->refresh());

            return to_route('org.opportunities.index')->with('success', "“{$opportunity->title}” is now published.");
        }

        return back()->with('success', 'Changes saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'organization' => $this->organizationSummary($this->organization($request)),
            'options' => [
                'categories' => OpportunityCategory::options(),
                'educationLevels' => EducationLevel::options(),
                'employmentTypes' => EmploymentType::options(),
                'workSetups' => WorkSetup::options(),
                'assistanceTypes' => AssistanceType::options(),
                'classifications' => Classification::options(),
            ],
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
            'skillSuggestions' => Skill::orderBy('name')->pluck('name'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Opportunity $opportunity): array
    {
        $opportunity->load(['jobDetail', 'scholarshipDetail', 'livelihoodDetail', 'requirements', 'requiredDocuments', 'skills']);

        return [
            'id' => $opportunity->id,
            'status' => $opportunity->displayStatus(),
            'status_label' => $opportunity->displayStatusLabel(),
            'stored_status' => $opportunity->status->value,
            'suspension_reason' => $opportunity->status === OpportunityStatus::Suspended ? $opportunity->suspension_reason : null,
            'was_published' => $opportunity->published_at !== null,
            'category' => $opportunity->category->value,
            'title' => $opportunity->title,
            'description' => $opportunity->description,
            'qualifications' => $opportunity->qualifications,
            'benefits' => $opportunity->benefits,
            'application_instructions' => $opportunity->application_instructions,
            'min_education' => $opportunity->min_education?->value,
            'barangay_id' => $opportunity->barangay_id,
            'location_text' => $opportunity->location_text,
            'is_remote' => $opportunity->is_remote,
            'application_start' => $opportunity->application_start?->toDateString(),
            'application_deadline' => $opportunity->application_deadline?->toDateString(),
            'slots' => $opportunity->slots,
            'contact_email' => $opportunity->contact_email,
            'contact_number' => $opportunity->contact_number,
            'requirements' => $opportunity->requirements->pluck('description'),
            'documents' => $opportunity->requiredDocuments->map->only(['id', 'name', 'is_required']),
            'skills' => $opportunity->skills->pluck('name'),
            'job' => $opportunity->jobDetail ? [
                'employment_type' => $opportunity->jobDetail->employment_type->value,
                'salary_min' => $opportunity->jobDetail->salary_min,
                'salary_max' => $opportunity->jobDetail->salary_max,
                'work_setup' => $opportunity->jobDetail->work_setup?->value,
                'min_experience_years' => $opportunity->jobDetail->min_experience_years,
            ] : null,
            'scholarship' => $opportunity->scholarshipDetail ? [
                'education_level' => $opportunity->scholarshipDetail->education_level->value,
                'eligible_courses' => $opportunity->scholarshipDetail->eligible_courses ?? [],
                'academic_requirements' => $opportunity->scholarshipDetail->academic_requirements,
                'financial_requirements' => $opportunity->scholarshipDetail->financial_requirements,
            ] : null,
            'livelihood' => $opportunity->livelihoodDetail ? [
                'assistance_type' => $opportunity->livelihoodDetail->assistance_type->value,
                'target_beneficiaries' => $opportunity->livelihoodDetail->target_beneficiaries,
                'assistance_provided' => $opportunity->livelihoodDetail->assistance_provided,
                'training_requirements' => $opportunity->livelihoodDetail->training_requirements,
                'target_classifications' => $opportunity->livelihoodDetail->target_classifications ?? [],
            ] : null,
        ];
    }
}
