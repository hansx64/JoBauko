<?php

namespace App\Services\Accounts;

use App\Models\SmsLog;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * One-time codes for verifying a user's mobile number. Only a hash of the code
 * is stored, it expires, and it is discarded after too many wrong attempts.
 */
class MobileVerificationService
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function sendCode(User $user): bool
    {
        $code = (string) random_int(100000, 999999);
        $ttl = config('jobauko.mobile_verification.code_ttl_minutes');

        Cache::put($this->cacheKey($user), [
            'hash' => Hash::make($code),
            'mobile_number' => $user->mobile_number,
            'attempts' => 0,
        ], now()->addMinutes($ttl));

        $message = sprintf(
            '%s: Your verification code is %s. It expires in %d minutes. Do not share this code.',
            config('app.name'),
            $code,
            $ttl,
        );

        $result = $this->gateway->send($user->mobile_number, $message);

        SmsLog::create([
            'user_id' => $user->id,
            'mobile_number' => $user->mobile_number,
            // Never store the code itself in the SMS history.
            'message' => str_replace($code, '******', $message),
            'topic' => 'verification',
            'status' => $result->successful ? 'sent' : 'failed',
            'provider' => $this->gateway->name(),
            'provider_message_id' => $result->providerMessageId,
            'error' => $result->error,
            'sent_at' => $result->successful ? now() : null,
        ]);

        return $result->successful;
    }

    /**
     * Check a submitted code. Returns false for wrong, expired or exhausted
     * codes, and for codes issued to a number the user has since changed.
     */
    public function verify(User $user, string $code): bool
    {
        $key = $this->cacheKey($user);
        $pending = Cache::get($key);

        if (! $pending || $pending['mobile_number'] !== $user->mobile_number) {
            return false;
        }

        if (! Hash::check($code, $pending['hash'])) {
            $pending['attempts']++;

            $pending['attempts'] >= config('jobauko.mobile_verification.max_attempts')
                ? Cache::forget($key)
                : Cache::put($key, $pending, now()->addMinutes(config('jobauko.mobile_verification.code_ttl_minutes')));

            return false;
        }

        Cache::forget($key);

        $user->forceFill(['mobile_verified_at' => now()])->save();

        return true;
    }

    public function hasPendingCode(User $user): bool
    {
        $pending = Cache::get($this->cacheKey($user));

        return $pending !== null && $pending['mobile_number'] === $user->mobile_number;
    }

    private function cacheKey(User $user): string
    {
        return "mobile-verification:{$user->id}";
    }
}
