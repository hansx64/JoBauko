<?php

namespace App\Reports;

use App\Enums\AccountType;
use App\Enums\Classification;
use App\Enums\Gender;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Accounts created in the period. Aggregated only: no individual is listed.
 */
class RegisteredUsersReport extends Report
{
    /** Age bands, with the youth band (15–30) used in LGU programs split out. */
    private const AGE_GROUPS = [
        'Below 18' => [0, 17],
        '18–24' => [18, 24],
        '25–30' => [25, 30],
        '31–44' => [31, 44],
        '45–59' => [45, 59],
        '60 and above' => [60, 200],
    ];

    public function key(): string
    {
        return 'registered-users';
    }

    public function title(): string
    {
        return 'Registered users';
    }

    public function description(): string
    {
        return 'New accounts by type, seeker classification, sex and age group.';
    }

    public function periodBasis(): string
    {
        return 'Date the account was created';
    }

    public function build(ReportPeriod $period): ReportResult
    {
        $users = fn (): Builder => DB::table('users')->whereNull('users.deleted_at')->whereBetween('users.created_at', [$period->from, $period->to]);
        $seekers = fn (): Builder => $users()->where('users.account_type', AccountType::Seeker->value)->join('applicant_profiles', 'applicant_profiles.user_id', '=', 'users.id');

        $byType = $users()->selectRaw('account_type, count(*) as total, sum(is_active = 0) as inactive')->groupBy('account_type')->get()->keyBy('account_type');
        $total = (int) $byType->sum('total');
        $byClassification = $seekers()
            ->selectRaw('classification, gender, count(*) as total')
            ->groupBy('classification', 'gender')
            ->get();
        $monthly = $users()->selectRaw(self::monthExpression('users.created_at').' as month, count(*) as total')->groupBy('month')->pluck('total', 'month');
        $seekerTotal = (int) ($byType[AccountType::Seeker->value]->total ?? 0);
        $verifiedMobile = $users()->where('account_type', AccountType::Seeker->value)->whereNotNull('mobile_verified_at')->count();

        $classificationRows = array_map(function (Classification $c) use ($byClassification) {
            $rows = $byClassification->where('classification', $c->value);

            return [
                'classification' => $c->label(),
                'female' => (int) $rows->where('gender', Gender::Female->value)->sum('total'),
                'male' => (int) $rows->where('gender', Gender::Male->value)->sum('total'),
                'other' => (int) $rows->whereNotIn('gender', [Gender::Female->value, Gender::Male->value])->sum('total'),
                'total' => (int) $rows->sum('total'),
            ];
        }, Classification::cases());

        return new ReportResult(
            summary: [
                ['label' => 'New accounts', 'value' => $total],
                ['label' => 'Opportunity seekers', 'value' => $seekerTotal],
                ['label' => 'Organization personnel', 'value' => (int) ($byType[AccountType::Organization->value]->total ?? 0)],
                ['label' => 'Seekers with a verified mobile', 'value' => $this->percent($verifiedMobile, $seekerTotal)],
            ],
            charts: [
                ['title' => 'New accounts per month', 'type' => 'monthly', 'unit' => ['account', 'accounts'], 'rows' => $this->monthly($period, $monthly)],
                ['title' => 'Seekers by classification', 'type' => 'ranked', 'rows' => collect($classificationRows)->map(fn ($r) => ['label' => $r['classification'], 'value' => $r['total']])->sortByDesc('value')->values()->all()],
            ],
            tables: [
                new ReportTable('By account type', ['type' => 'Account type', 'total' => 'New accounts', 'active' => 'Active', 'inactive' => 'Deactivated'], array_map(fn (AccountType $t) => [
                    'type' => $t->label(),
                    'total' => (int) ($byType[$t->value]->total ?? 0),
                    'active' => (int) ($byType[$t->value]->total ?? 0) - (int) ($byType[$t->value]->inactive ?? 0),
                    'inactive' => (int) ($byType[$t->value]->inactive ?? 0),
                ], AccountType::cases()), ['total', 'active', 'inactive']),
                new ReportTable(
                    'Seekers by classification and sex',
                    ['classification' => 'Classification', 'female' => 'Female', 'male' => 'Male', 'other' => 'Other / not stated', 'total' => 'Total'],
                    $classificationRows,
                    ['female', 'male', 'other', 'total'],
                    'Sex as stated by the seeker in their profile.',
                ),
                new ReportTable('Seekers by age group', ['group' => 'Age group', 'total' => 'Seekers'], $this->ageGroups($seekers, $period), ['total'], 'Age on the last day of the period. Seekers without a date of birth are counted as not stated.'),
                new ReportTable('New accounts per month', ['month' => 'Month', 'total' => 'New accounts'], array_map(
                    fn (array $row) => ['month' => date('M Y', strtotime($row['label'].'-01')), 'total' => $row['value']],
                    $this->monthly($period, $monthly),
                ), ['total']),
            ],
        );
    }

    /**
     * @param  callable(): Builder  $seekers
     * @return list<array{group: string, total: int}>
     */
    private function ageGroups(callable $seekers, ReportPeriod $period): array
    {
        $ages = $seekers()
            ->selectRaw('timestampdiff(year, applicant_profiles.date_of_birth, ?) as age, count(*) as total', [$period->to->toDateString()])
            ->groupBy('age')
            ->pluck('total', 'age');

        $rows = [];
        foreach (self::AGE_GROUPS as $label => [$min, $max]) {
            $rows[] = ['group' => $label, 'total' => (int) $ages->filter(fn ($total, $age) => $age !== '' && (int) $age >= $min && (int) $age <= $max)->sum()];
        }
        $rows[] = ['group' => 'Not stated', 'total' => (int) ($ages[''] ?? 0)];

        return $rows;
    }
}
