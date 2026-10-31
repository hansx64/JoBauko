<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\Classification;
use App\Http\Controllers\Controller;
use App\Models\ApplicantProfile;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Platform overview. Extended with opportunity and application figures
     * as those modules are built.
     */
    public function __invoke(): Response
    {
        $accountCounts = User::query()
            ->selectRaw('account_type, count(*) as total')
            ->groupBy('account_type')
            ->pluck('total', 'account_type');

        $classificationCounts = ApplicantProfile::query()
            ->selectRaw('classification, count(*) as total')
            ->groupBy('classification')
            ->pluck('total', 'classification');

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'totalUsers' => $accountCounts->sum(),
                'seekers' => (int) ($accountCounts[AccountType::Seeker->value] ?? 0),
                'organizationUsers' => (int) ($accountCounts[AccountType::Organization->value] ?? 0),
                'inactiveUsers' => User::where('is_active', false)->count(),
            ],
            'classifications' => array_map(fn (Classification $c) => [
                'label' => $c->label(),
                'total' => (int) ($classificationCounts[$c->value] ?? 0),
            ], Classification::cases()),
        ]);
    }
}
