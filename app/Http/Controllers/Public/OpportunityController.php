<?php

namespace App\Http\Controllers\Public;

use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\OpportunityCategory;
use App\Enums\ReportReason;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Services\Recommendation\MatchRefresher;
use App\Services\Recommendation\MatchScorer;
use App\Services\Recommendation\SeekerSnapshot;
use App\Support\OpportunityCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    private const SORTS = ['newest', 'deadline', 'salary', 'match'];

    public function index(Request $request, MatchRefresher $matches): Response
    {
        $user = $request->user();
        $isSeeker = $user?->isSeeker() ?? false;

        $filters = [
            'q' => trim((string) $request->query('q')),
            'category' => OpportunityCategory::tryFrom((string) $request->query('category'))?->value,
            'barangay' => $request->integer('barangay') ?: null,
            'remote' => $request->boolean('remote'),
            'organization' => $request->integer('organization') ?: null,
            'education' => EducationLevel::tryFrom((string) $request->query('education'))?->value,
            'skill' => trim((string) $request->query('skill')) ?: null,
            'employment_type' => EmploymentType::tryFrom((string) $request->query('employment_type'))?->value,
            'salary_min' => $request->integer('salary_min') ?: null,
            'closing' => in_array($request->query('closing'), ['7', '30'], true) ? $request->query('closing') : null,
            'status' => $request->query('status') === 'all' ? 'all' : 'open',
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : ($isSeeker ? 'match' : 'newest'),
        ];

        if ($filters['sort'] === 'match' && ! $isSeeker) {
            $filters['sort'] = 'newest';
        }

        if ($isSeeker) {
            $matches->ensureFresh($user);
        }

        $query = $this->filteredQuery($filters)->with(['organization', 'barangay', 'jobDetail']);

        match ($filters['sort']) {
            'deadline' => $query->orderBy('application_deadline'),
            'salary' => $query->orderByDesc(
                DB::table('job_details')->selectRaw('coalesce(salary_max, salary_min)')->whereColumn('job_details.opportunity_id', 'opportunities.id')
            ),
            'match' => $query->orderByDesc(
                DB::table('opportunity_matches')->select('score')
                    ->whereColumn('opportunity_matches.opportunity_id', 'opportunities.id')
                    ->where('opportunity_matches.user_id', $user->id)
            ),
            default => $query->latest('published_at'),
        };

        $page = $query->orderByDesc('opportunities.id')->paginate(12)->withQueryString();

        $matchMap = $isSeeker ? $this->matchesFor($user->id, $page->pluck('id')->all()) : [];
        $savedIds = $isSeeker ? $user->savedOpportunities()->whereIn('opportunities.id', $page->pluck('id'))->pluck('opportunities.id')->all() : [];

        return Inertia::render('opportunities/index', [
            'opportunities' => $page->through(fn (Opportunity $o) => OpportunityCard::make(
                $o,
                $matchMap[$o->id] ?? null,
                in_array($o->id, $savedIds, true),
            )),
            'filters' => $filters,
            'isSeeker' => $isSeeker,
            'options' => [
                'categories' => OpportunityCategory::options(),
                'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
                'organizations' => Organization::query()
                    ->where('verification_status', VerificationStatus::Verified)
                    ->whereHas('opportunities', fn (Builder $q) => $q->visible())
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'educationLevels' => EducationLevel::options(),
                'employmentTypes' => EmploymentType::options(),
                'skills' => Skill::query()
                    ->whereHas('opportunities', fn (Builder $q) => $q->visible())
                    ->orderBy('name')
                    ->pluck('name'),
            ],
        ]);
    }

    public function show(Request $request, Opportunity $opportunity, MatchScorer $scorer): Response
    {
        abort_unless(Opportunity::query()->visible()->whereKey($opportunity->id)->exists(), 404);

        $opportunity->load([
            'organization', 'barangay', 'jobDetail', 'scholarshipDetail', 'livelihoodDetail',
            'requirements', 'requiredDocuments', 'skills',
        ]);

        $user = $request->user();
        $isSeeker = $user?->isSeeker() ?? false;
        $application = $isSeeker ? $user->applications()->withTrashed()->where('opportunity_id', $opportunity->id)->first() : null;

        return Inertia::render('opportunities/show', [
            'opportunity' => [
                ...OpportunityCard::make(
                    $opportunity,
                    // Scored live so the explanation always reflects the current profile.
                    $isSeeker ? $scorer->score(SeekerSnapshot::fromUser($user), $opportunity)->toArray() : null,
                    $isSeeker && $user->savedOpportunities()->whereKey($opportunity->id)->exists(),
                ),
                'description' => $opportunity->description,
                'qualifications' => $opportunity->qualifications,
                'benefits' => $opportunity->benefits,
                'application_instructions' => $opportunity->application_instructions,
                'min_education' => $opportunity->min_education?->label(),
                'application_start' => $opportunity->application_start?->toDateString(),
                'published_at' => $opportunity->published_at?->toIso8601String(),
                'contact_email' => $opportunity->contact_email,
                'contact_number' => $opportunity->contact_number,
                'requirements' => $opportunity->requirements->pluck('description'),
                'documents' => $opportunity->requiredDocuments->map->only(['name', 'is_required']),
                'skills' => $opportunity->skills->pluck('name'),
                'job' => $opportunity->jobDetail ? [
                    'employment_type' => $opportunity->jobDetail->employment_type->label(),
                    'work_setup' => $opportunity->jobDetail->work_setup?->label(),
                    'min_experience_years' => $opportunity->jobDetail->min_experience_years,
                ] : null,
                'scholarship' => $opportunity->scholarshipDetail ? [
                    'education_level' => $opportunity->scholarshipDetail->education_level->label(),
                    'eligible_courses' => $opportunity->scholarshipDetail->eligible_courses ?? [],
                    'academic_requirements' => $opportunity->scholarshipDetail->academic_requirements,
                    'financial_requirements' => $opportunity->scholarshipDetail->financial_requirements,
                ] : null,
                'livelihood' => $opportunity->livelihoodDetail ? [
                    'assistance_type' => $opportunity->livelihoodDetail->assistance_type->label(),
                    'target_beneficiaries' => $opportunity->livelihoodDetail->target_beneficiaries,
                    'assistance_provided' => $opportunity->livelihoodDetail->assistance_provided,
                    'training_requirements' => $opportunity->livelihoodDetail->training_requirements,
                ] : null,
                'organization_details' => [
                    'type' => $opportunity->organization->type->label(),
                    'description' => $opportunity->organization->description,
                    'official_email' => $opportunity->organization->official_email,
                    'contact_number' => $opportunity->organization->contact_number,
                    'office_address' => $opportunity->organization->office_address,
                ],
            ],
            'isSeeker' => $isSeeker,
            'application' => $application ? [
                'id' => $application->id,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'submitted_at' => $application->submitted_at->toIso8601String(),
            ] : null,
            'reporting' => $this->reporting($user, $opportunity),
        ]);
    }

    /**
     * Signed-in users outside the publishing organization can report a
     * listing; administrators act on it directly instead.
     *
     * @return array<string, mixed>|null
     */
    private function reporting(?User $user, Opportunity $opportunity): ?array
    {
        if ($user === null || $user->isAdmin() || $user->organizationMembership?->organization_id === $opportunity->organization_id) {
            return null;
        }

        return [
            'alreadyReported' => $opportunity->reports()->open()->where('reporter_id', $user->id)->exists(),
            'reasons' => array_map(fn (ReportReason $r) => [
                'value' => $r->value,
                'label' => $r->label(),
                'requires_details' => $r->requiresDetails(),
            ], ReportReason::cases()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Opportunity>
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = Opportunity::query()->visible();

        if ($filters['status'] === 'open') {
            $query->open();
        }

        if ($filters['q'] !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';

            $query->where(fn (Builder $q) => $q
                ->whereFullText(['title', 'description', 'qualifications'], $filters['q'])
                ->orWhere('title', 'like', $like)
                ->orWhereHas('organization', fn (Builder $o) => $o->where('name', 'like', $like))
                ->orWhereHas('skills', fn (Builder $s) => $s->where('name', 'like', $like)));
        }

        $query
            ->when($filters['category'], fn (Builder $q, string $category) => $q->where('category', $category))
            ->when($filters['barangay'], fn (Builder $q, int $barangay) => $q->where('barangay_id', $barangay))
            ->when($filters['remote'], fn (Builder $q) => $q->where('is_remote', true))
            ->when($filters['organization'], fn (Builder $q, int $organization) => $q->where('organization_id', $organization))
            ->when($filters['skill'], fn (Builder $q, string $skill) => $q->whereHas('skills', fn (Builder $s) => $s->where('name', $skill)))
            ->when($filters['employment_type'], fn (Builder $q, string $type) => $q->whereHas('jobDetail', fn (Builder $j) => $j->where('employment_type', $type)))
            ->when($filters['salary_min'], fn (Builder $q, int $salary) => $q->whereHas('jobDetail', fn (Builder $j) => $j->whereRaw('coalesce(salary_max, salary_min) >= ?', [$salary])))
            ->when($filters['closing'], fn (Builder $q, string $days) => $q->whereDate('application_deadline', '<=', today()->addDays((int) $days)));

        // "My education": opportunities whose requirement this level meets.
        if ($filters['education']) {
            $rank = EducationLevel::from($filters['education'])->rank();
            $allowed = array_values(array_map(
                fn (EducationLevel $level) => $level->value,
                array_filter(EducationLevel::cases(), fn (EducationLevel $level) => $level->rank() <= $rank),
            ));

            $query->where(fn (Builder $q) => $q->whereNull('min_education')->orWhereIn('min_education', $allowed));
        }

        return $query;
    }

    /**
     * @param  list<int>  $opportunityIds
     * @return array<int, array{score: int, reasons: list<string>}>
     */
    private function matchesFor(int $userId, array $opportunityIds): array
    {
        return DB::table('opportunity_matches')
            ->where('user_id', $userId)
            ->whereIn('opportunity_id', $opportunityIds)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->opportunity_id => [
                'score' => (int) $row->score,
                'reasons' => json_decode($row->reasons, true)['reasons'] ?? [],
            ]])
            ->all();
    }
}
