<?php

namespace App\Services\Privacy;

use App\Enums\ConsentType;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Http\Request;

/**
 * Records consent grants and withdrawals as an auditable history: every grant
 * keeps the statement version, time and origin; withdrawals are timestamped
 * rather than deleted.
 */
class ConsentRecorder
{
    public function grant(User $user, ConsentType $type, ?Request $request = null): UserConsent
    {
        $active = $user->consents()
            ->where('type', $type)
            ->where('version', $type->currentVersion())
            ->whereNull('withdrawn_at')
            ->first();

        return $active ?? $user->consents()->create([
            'type' => $type,
            'version' => $type->currentVersion(),
            'granted_at' => now(),
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
        ]);
    }

    public function withdraw(User $user, ConsentType $type): void
    {
        $user->consents()
            ->where('type', $type)
            ->whereNull('withdrawn_at')
            ->update(['withdrawn_at' => now()]);
    }
}
