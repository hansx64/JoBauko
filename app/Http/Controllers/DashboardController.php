<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\ConsentType;
use App\Services\Profiles\ProfileCompletion;
use App\Support\MobileNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Seekers get their dashboard here; other account types are sent to the
     * dashboard of their own area.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        return match ($user->account_type) {
            AccountType::Admin => to_route('admin.dashboard'),
            AccountType::Organization => to_route('org.dashboard'),
            AccountType::Seeker => $this->seekerDashboard($request),
        };
    }

    private function seekerDashboard(Request $request): Response
    {
        $user = $request->user()->load(['applicantProfile', 'barangay', 'notificationPreferences']);

        return Inertia::render('dashboard', [
            'completion' => app(ProfileCompletion::class)->for($user),
            'classification' => $user->applicantProfile?->classification->label(),
            'barangay' => $user->barangay?->name,
            'mobile' => [
                'masked' => $user->mobile_number ? MobileNumber::mask($user->mobile_number) : null,
                'verified' => $user->hasVerifiedMobile(),
            ],
            'smsConsent' => $user->hasActiveConsent(ConsentType::Sms),
            'selectedTopics' => $user->notificationPreferences
                ->filter(fn ($preference) => $preference->sms || $preference->email)
                ->map(fn ($preference) => $preference->topic->label())
                ->values(),
        ]);
    }
}
