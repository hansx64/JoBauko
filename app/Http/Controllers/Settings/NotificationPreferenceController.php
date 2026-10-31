<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ConsentType;
use App\Enums\NotificationTopic;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Services\Privacy\ConsentRecorder;
use App\Support\MobileNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A seeker's choice of email and SMS per topic, and their SMS consent.
 * In-app notifications are always on.
 */
class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $preferences = $user->notificationPreferences->keyBy(fn (NotificationPreference $p) => $p->topic->value);

        return Inertia::render('settings/notifications', [
            'topics' => array_map(fn (NotificationTopic $topic) => [
                'value' => $topic->value,
                'label' => $topic->label(),
                'email' => (bool) $preferences->get($topic->value)?->email,
                'sms' => (bool) $preferences->get($topic->value)?->sms,
            ], NotificationTopic::cases()),
            'smsConsent' => $user->hasActiveConsent(ConsentType::Sms),
            'emailVerified' => $user->email_verified_at !== null,
            'mobile' => [
                'masked' => $user->mobile_number ? MobileNumber::mask($user->mobile_number) : null,
                'verified' => $user->hasVerifiedMobile(),
            ],
        ]);
    }

    public function update(Request $request, ConsentRecorder $consents): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.email' => ['required', 'boolean'],
            'preferences.*.sms' => ['required', 'boolean'],
            'sms_consent' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $validated, $consents, $request) {
            foreach (NotificationTopic::cases() as $topic) {
                $choice = $validated['preferences'][$topic->value] ?? null;

                if ($choice === null) {
                    continue;
                }

                $user->notificationPreferences()->updateOrCreate(
                    ['topic' => $topic],
                    ['email' => (bool) $choice['email'], 'sms' => (bool) $choice['sms'], 'in_app' => true, 'source' => 'user'],
                );
            }

            // Consent changes are recorded with the statement version.
            $validated['sms_consent']
                ? $consents->grant($user, ConsentType::Sms, $request)
                : $consents->withdraw($user, ConsentType::Sms);
        });

        return back()->with('success', 'Notification settings saved.');
    }
}
