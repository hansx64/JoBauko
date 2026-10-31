<?php

namespace App\Services\Accounts;

use App\Enums\AccountType;
use App\Enums\Classification;
use App\Enums\ConsentType;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Privacy\ConsentRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeekerRegistrationService
{
    public function __construct(
        private readonly ConsentRecorder $consents,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    /**
     * Create a seeker account with its profile, consent records and default
     * notification preferences, all or nothing.
     *
     * @param  array{name: string, email: string, mobile_number: string, password: string, barangay_id: int, classification: string, sms_consent?: bool}  $data
     */
    public function register(array $data, Request $request): User
    {
        return DB::transaction(function () use ($data, $request) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile_number' => $data['mobile_number'],
                'password' => $data['password'],
                'barangay_id' => $data['barangay_id'],
            ]);
            $user->account_type = AccountType::Seeker;
            $user->save();

            $classification = Classification::from($data['classification']);

            $user->applicantProfile()->create(['classification' => $classification]);

            $this->consents->grant($user, ConsentType::PrivacyNotice, $request);

            if ($data['sms_consent'] ?? false) {
                $this->consents->grant($user, ConsentType::Sms, $request);
            }

            $this->preferences->applyClassificationDefaults($user, $classification);

            return $user;
        });
    }
}
