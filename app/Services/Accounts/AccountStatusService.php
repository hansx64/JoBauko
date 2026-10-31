<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Notifications\AccountStatusChanged;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Administrator deactivation and reactivation of accounts. Accounts are
 * never deleted here: their records stay for the audit trail and the
 * person can be let back in.
 */
class AccountStatusService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function deactivate(User $user, User $admin, string $reason): void
    {
        // Admins can't lock themselves out, which also guarantees at least
        // one active administrator remains: the one acting.
        if ($user->is($admin)) {
            throw ValidationException::withMessages(['account' => 'You cannot deactivate your own account.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['account' => 'This account is already deactivated.']);
        }

        DB::transaction(function () use ($user, $admin, $reason) {
            $user->forceFill([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivation_reason' => $reason,
                'deactivated_by' => $admin->id,
            ])->save();

            // End every open session now instead of on the next request.
            UserSessions::end($user);

            $this->audit->log('user.deactivated', $user, ['is_active' => true], ['is_active' => false, 'reason' => $reason], $user->organizationMembership?->organization_id);
        });

        $user->notify(new AccountStatusChanged(active: false, reason: $reason));
    }

    public function reactivate(User $user): void
    {
        if ($user->is_active) {
            throw ValidationException::withMessages(['account' => 'This account is already active.']);
        }

        $user->forceFill([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivation_reason' => null,
            'deactivated_by' => null,
        ])->save();

        $this->audit->log('user.reactivated', $user, ['is_active' => false], ['is_active' => true], $user->organizationMembership?->organization_id);

        $user->notify(new AccountStatusChanged(active: true));
    }
}
