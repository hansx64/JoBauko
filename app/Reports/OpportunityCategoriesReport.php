<?php

namespace App\Reports;

use App\Enums\ApplicationStatus;
use App\Enums\AssistanceType;
use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\OpportunityCategory;
use App\Enums\TrainingType;
use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;

/**
 * Supply and demand per category: what was offered in the period and how
 * many people applied and were approved.
 */
class OpportunityCategoriesReport extends Report
{
    public function key(): string
    {
        return 'opportunity-categories';
    }

    public function title(): string
    {
        return 'Opportunity categories';
    }

    public function description(): string
    {
        return 'Jobs, scholarships, livelihood programs, and trainings and seminars compared, with breakdowns by employment type, education level, assistance type and training type.';
    }

    public function periodBasis(): string
    {
        return 'Publication date for opportunities; submission and approval dates for applications';
    }

    public function build(ReportPeriod $period): ReportResult
    {
        $published = DB::table('opportunities')->whereNull('deleted_at')->whereBetween('published_at', [$period->from, $period->to]);
        $opportunities = (clone $published)->selectRaw('category, count(*) as total, sum(slots) as slots')->groupBy('category')->get()->keyBy('category');
        $applications = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->whereNull('applications.deleted_at')
            ->whereBetween('applications.submitted_at', [$period->from, $period->to])
            ->selectRaw('opportunities.category, count(*) as total')
            ->groupBy('opportunities.category')
            ->pluck('total', 'category');
        $approved = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->whereNull('applications.deleted_at')
            ->where('applications.status', ApplicationStatus::Approved->value)
            ->whereBetween('applications.decided_at', [$period->from, $period->to])
            ->selectRaw('opportunities.category, count(*) as total')
            ->groupBy('opportunities.category')
            ->pluck('total', 'category');
        $openToday = Opportunity::query()->visible()->open()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

        $rows = array_map(fn (OpportunityCategory $c) => [
            'category' => $c->label(),
            'published' => (int) ($opportunities[$c->value]->total ?? 0),
            'open' => (int) ($openToday[$c->value] ?? 0),
            'slots' => (int) ($opportunities[$c->value]->slots ?? 0),
            'applications' => (int) ($applications[$c->value] ?? 0),
            'approved' => (int) ($approved[$c->value] ?? 0),
        ], OpportunityCategory::cases());

        return new ReportResult(
            summary: array_map(fn (array $r) => ['label' => "{$r['category']} opportunities published", 'value' => $r['published']], $rows),
            charts: [
                ['title' => 'Applications by category', 'type' => 'ranked', 'rows' => collect($rows)->map(fn ($r) => ['label' => $r['category'], 'value' => $r['applications']])->sortByDesc('value')->values()->all()],
            ],
            tables: [
                new ReportTable(
                    'Categories',
                    ['category' => 'Category', 'published' => 'Published', 'open' => 'Open today', 'slots' => 'Slots offered', 'applications' => 'Applications', 'approved' => 'Approved'],
                    $rows,
                    ['published', 'open', 'slots', 'applications', 'approved'],
                    '“Open today” is the current number of listings accepting applications, regardless of the period.',
                ),
                $this->breakdown('Jobs by employment type', 'Employment type', 'job_details', 'employment_type', EmploymentType::cases(), $period),
                $this->breakdown('Scholarships by education level', 'Education level', 'scholarship_details', 'education_level', EducationLevel::cases(), $period),
                $this->breakdown('Livelihood programs by assistance type', 'Assistance type', 'livelihood_details', 'assistance_type', AssistanceType::cases(), $period),
                $this->breakdown('Trainings and seminars by type', 'Type', 'training_details', 'training_type', TrainingType::cases(), $period),
            ],
        );
    }

    /**
     * @param  list<EmploymentType|EducationLevel|AssistanceType|TrainingType>  $cases
     */
    private function breakdown(string $title, string $heading, string $table, string $column, array $cases, ReportPeriod $period): ReportTable
    {
        $counts = DB::table($table)
            ->join('opportunities', 'opportunities.id', '=', "{$table}.opportunity_id")
            ->leftJoin('applications', fn ($j) => $j->on('applications.opportunity_id', '=', 'opportunities.id')->whereNull('applications.deleted_at'))
            ->whereNull('opportunities.deleted_at')
            ->whereBetween('opportunities.published_at', [$period->from, $period->to])
            ->selectRaw("{$table}.{$column} as type, count(distinct opportunities.id) as opportunities, count(applications.id) as applications")
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return new ReportTable(
            $title,
            ['type' => $heading, 'opportunities' => 'Published', 'applications' => 'Applications'],
            array_map(fn ($case) => [
                'type' => $case->label(),
                'opportunities' => (int) ($counts[$case->value]->opportunities ?? 0),
                'applications' => (int) ($counts[$case->value]->applications ?? 0),
            ], $cases),
            ['opportunities', 'applications'],
            'Applications here are all applications to these listings, whenever submitted.',
        );
    }
}
