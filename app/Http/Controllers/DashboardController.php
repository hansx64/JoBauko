<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\ConsentType;
use App\Enums\OpportunityCategory;
use App\Models\Opportunity;
use App\Services\Profiles\ProfileCompletion;
use App\Services\Recommendation\MatchRefresher;
use App\Services\Recommendation\MatchScorer;
use App\Support\MobileNumber;
use App\Support\OpportunityCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $savedIds = $user->savedOpportunities()->pluck('opportunities.id')->all();
        $card = fn (Opportunity $o, ?array $match = null) => OpportunityCard::make($o, $match, in_array($o->id, $savedIds, true));

        app(MatchRefresher::class)->ensureFresh($user);

        $matches = DB::table('opportunity_matches')
            ->where('user_id', $user->id)
            ->where('score', '>=', MatchScorer::RECOMMENDATION_THRESHOLD)
            ->orderByDesc('score')
            ->limit(6)
            ->get()
            ->keyBy('opportunity_id');

        $recommended = Opportunity::query()->visible()->open()
            ->whereIn('id', $matches->keys())
            ->with(['organization', 'barangay', 'jobDetail'])
            ->get()
            ->sortByDesc(fn (Opportunity $o) => $matches[$o->id]->score)
            ->values()
            ->map(fn (Opportunity $o) => $card($o, [
                'score' => (int) $matches[$o->id]->score,
                'reasons' => json_decode($matches[$o->id]->reasons, true)['reasons'] ?? [],
            ]));

        $latest = collect(OpportunityCategory::cases())->mapWithKeys(fn (OpportunityCategory $category) => [
            $category->value => Opportunity::query()->visible()->open()
                ->where('category', $category)
                ->with(['organization', 'barangay', 'jobDetail'])
                ->latest('published_at')
                ->limit(3)
                ->get()
                ->map(fn (Opportunity $o) => $card($o)),
        ]);

        $saved = $user->savedOpportunities()->visible()
            ->with(['organization', 'barangay', 'jobDetail'])
            ->orderByPivot('created_at', 'desc')
            ->limit(4)
            ->get();

        return Inertia::render('dashboard', [
            'recommended' => $recommended,
            'latest' => $latest,
            'saved' => $saved->map(fn (Opportunity $o) => $card($o)),
            'savedCount' => count($savedIds),
            'deadlines' => $user->savedOpportunities()->visible()->open()
                ->whereDate('application_deadline', '<=', today()->addDays(14))
                ->orderBy('application_deadline')
                ->limit(5)
                ->get(['opportunities.id', 'slug', 'title', 'application_deadline'])
                ->map(fn (Opportunity $o) => [
                    'id' => $o->id,
                    'slug' => $o->slug,
                    'title' => $o->title,
                    'deadline' => $o->application_deadline->toDateString(),
                    'days_left' => (int) today()->diffInDays($o->application_deadline),
                ]),
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
