<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\ApplicationStatus;
use App\Enums\Classification;
use App\Enums\OpportunityCategory;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Barangay;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use App\Support\DailyCounts;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Platform overview for the municipal administrators: who uses the
     * portal, what is offered, and what needs attention.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => $this->stats(),
            'attention' => [
                'pendingOrganizations' => Organization::where('verification_status', VerificationStatus::Pending)->count(),
                'openReports' => Report::query()->open()->count(),
                'stalledApplications' => Application::query()->stalled()->count(),
                'stalledDays' => Application::STALLED_DAYS,
            ],
            'registrationsPerDay' => DailyCounts::lastDays(User::query()->toBase(), 'created_at'),
            'applicationsPerDay' => DailyCounts::lastDays(Application::query()->toBase(), 'submitted_at'),
            'classifications' => $this->classifications(),
            'seekersByBarangay' => $this->seekersByBarangay(),
            'openByCategory' => $this->openByCategory(),
            'applicationsByStatus' => $this->applicationsByStatus(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        $accountCounts = User::query()
            ->selectRaw('account_type, count(*) as total')
            ->groupBy('account_type')
            ->pluck('total', 'account_type');

        return [
            'totalUsers' => (int) $accountCounts->sum(),
            'seekers' => (int) ($accountCounts[AccountType::Seeker->value] ?? 0),
            'organizationUsers' => (int) ($accountCounts[AccountType::Organization->value] ?? 0),
            'inactiveUsers' => User::where('is_active', false)->count(),
            'verifiedOrganizations' => Organization::where('verification_status', VerificationStatus::Verified)->count(),
            'openOpportunities' => Opportunity::query()->visible()->open()->count(),
            'applications' => Application::count(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, value: int}>
     */
    private function classifications(): array
    {
        $counts = ApplicantProfile::query()
            ->whereHas('user')
            ->selectRaw('classification, count(*) as total')
            ->groupBy('classification')
            ->pluck('total', 'classification');

        return collect(Classification::cases())
            ->map(fn (Classification $c) => ['key' => $c->value, 'label' => $c->label(), 'value' => (int) ($counts[$c->value] ?? 0)])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * Every barangay is listed, including those with no seekers yet: for
     * outreach planning the gaps matter as much as the totals.
     *
     * @return list<array{key: string, label: string, value: int}>
     */
    private function seekersByBarangay(): array
    {
        $counts = User::query()
            ->where('account_type', AccountType::Seeker)
            ->selectRaw('barangay_id, count(*) as total')
            ->groupBy('barangay_id')
            ->pluck('total', 'barangay_id');

        $rows = Barangay::orderBy('name')->get(['id', 'name'])
            ->map(fn (Barangay $b) => ['key' => (string) $b->id, 'label' => $b->name, 'value' => (int) ($counts[$b->id] ?? 0)]);

        $unspecified = (int) ($counts[''] ?? 0);

        if ($unspecified > 0) {
            $rows->push(['key' => 'none', 'label' => 'Not specified', 'value' => $unspecified]);
        }

        return $rows->sortByDesc('value')->values()->all();
    }

    /**
     * @return list<array{key: string, label: string, value: int}>
     */
    private function openByCategory(): array
    {
        $counts = Opportunity::query()->visible()->open()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return collect(OpportunityCategory::cases())
            ->map(fn (OpportunityCategory $c) => ['key' => $c->value, 'label' => $c->label(), 'value' => (int) ($counts[$c->value] ?? 0)])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, value: int}>
     */
    private function applicationsByStatus(): array
    {
        $counts = Application::query()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(ApplicationStatus::cases())
            ->map(fn (ApplicationStatus $s) => ['key' => $s->value, 'label' => $s->label(), 'value' => (int) ($counts[$s->value] ?? 0)])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->all();
    }
}
