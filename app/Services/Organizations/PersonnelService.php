<?php

namespace App\Services\Organizations;

use App\Enums\AccountType;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adding personnel to an organization and changing their role or access.
 * Members are deactivated rather than deleted so the history of what they
 * did (status changes, remarks) stays attributable.
 */
class PersonnelService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create an account for a new member and email them an invitation to
     * set their password.
     */
    public function invite(Organization $organization, User $inviter, string $name, string $email, OrganizationRole $role): OrganizationMember
    {
        $member = DB::transaction(function () use ($organization, $inviter, $name, $email, $role) {
            $user = new User(['name' => $name, 'email' => Str::lower($email)]);
            // Unusable until the invitation is accepted.
            $user->password = Str::password(40);
            $user->account_type = AccountType::Organization;
            $user->save();

            $member = $organization->members()->create([
                'user_id' => $user->id,
                'role' => $role,
                'is_active' => true,
                'invited_by' => $inviter->id,
            ]);

            $this->audit->log('personnel.invited', $member, new: ['user_id' => $user->id, 'role' => $role->value], organizationId: $organization->id);

            return $member;
        });

        $member->user->notify(new OrganizationInvitation($member));

        return $member;
    }

    public function resendInvitation(OrganizationMember $member): void
    {
        if (! self::invitationPending($member)) {
            throw ValidationException::withMessages(['member' => 'This person has already set up their account.']);
        }

        $member->user->notify(new OrganizationInvitation($member));
        $this->audit->log('personnel.invitation_resent', $member, organizationId: $member->organization_id);
    }

    public function update(OrganizationMember $member, User $actor, OrganizationRole $role, bool $isActive): OrganizationMember
    {
        if ((int) $member->user_id === (int) $actor->id && ($role !== $member->role || $isActive !== $member->is_active)) {
            throw ValidationException::withMessages(['member' => 'You cannot change your own role or access. Ask another administrator.']);
        }

        return DB::transaction(function () use ($member, $role, $isActive) {
            // Lock the organization's members so two simultaneous changes
            // cannot both remove the last administrator.
            $activeAdmins = OrganizationMember::query()
                ->where('organization_id', $member->organization_id)
                ->where('role', OrganizationRole::Admin)
                ->where('is_active', true)
                ->lockForUpdate()
                ->pluck('id');

            $losesAdmin = $member->role === OrganizationRole::Admin && $member->is_active && ($role !== OrganizationRole::Admin || ! $isActive);

            if ($losesAdmin && $activeAdmins->count() <= 1) {
                throw ValidationException::withMessages(['member' => 'The organization must keep at least one active administrator.']);
            }

            $member->role = $role;
            $member->is_active = $isActive;
            $member->save();

            $this->audit->logChanges('personnel.updated', $member, $member->organization_id);

            return $member;
        });
    }

    /**
     * Invited personnel who have not set their password yet. Self-registered
     * administrators (no inviter) never count as invited.
     */
    public static function invitationPending(OrganizationMember $member): bool
    {
        return $member->invited_by !== null
            && $member->user !== null
            && $member->user->email_verified_at === null
            && $member->user->last_login_at === null;
    }
}
