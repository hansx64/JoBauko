<?php

namespace App\Services\Organizations;

use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationVerificationUpdated;
use App\Services\Audit\AuditLogger;
use App\Services\Recommendation\MatchRefresher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Administrator decisions on organizations. Each decision is audited and the
 * organization's personnel are notified.
 */
class OrganizationVerificationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(Organization $organization, User $admin): void
    {
        $this->transition($organization, $admin, [VerificationStatus::Pending, VerificationStatus::Rejected], VerificationStatus::Verified, null, 'organization.verified');
    }

    public function reject(Organization $organization, User $admin, string $reason): void
    {
        $this->transition($organization, $admin, [VerificationStatus::Pending], VerificationStatus::Rejected, $reason, 'organization.rejected');
    }

    public function suspend(Organization $organization, User $admin, string $reason): void
    {
        $this->transition($organization, $admin, [VerificationStatus::Verified, VerificationStatus::Pending], VerificationStatus::Suspended, $reason, 'organization.suspended');
    }

    public function reinstate(Organization $organization, User $admin): void
    {
        $this->transition($organization, $admin, [VerificationStatus::Suspended], VerificationStatus::Verified, null, 'organization.reinstated');
    }

    /**
     * @param  list<VerificationStatus>  $from
     */
    private function transition(Organization $organization, User $admin, array $from, VerificationStatus $to, ?string $reason, string $action): void
    {
        if (! in_array($organization->verification_status, $from, true)) {
            throw ValidationException::withMessages([
                'status' => "This organization is {$organization->verification_status->label()} and can't be changed that way.",
            ]);
        }

        DB::transaction(function () use ($organization, $admin, $to, $reason, $action) {
            $previous = $organization->verification_status;

            $organization->verification_status = $to;
            $organization->status_reason = $reason;

            if ($to === VerificationStatus::Verified) {
                $organization->verified_at = now();
                $organization->verified_by = $admin->id;
            }

            $organization->save();

            // Verification decides whether its opportunities are public.
            MatchRefresher::invalidateAll();

            $this->audit->log(
                $action,
                $organization,
                ['verification_status' => $previous->value],
                array_filter(['verification_status' => $to->value, 'reason' => $reason]),
                $organization->id,
            );
        });

        $recipients = $organization->members()->where('is_active', true)->with('user')->get()->pluck('user')->filter();

        Notification::send($recipients, new OrganizationVerificationUpdated($organization));
    }
}
