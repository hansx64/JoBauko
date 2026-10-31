<?php

namespace Tests\Feature\Organization;

use App\Enums\AccountType;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PersonnelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{organization: Organization, admin: User}
     */
    private function organization(): array
    {
        $admin = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember(OrganizationRole::Admin, $admin)->create();

        return ['organization' => $organization, 'admin' => $admin];
    }

    private function invite(User $admin, string $email = 'maria@coop.test', string $role = 'reviewer'): OrganizationMember
    {
        $this->actingAs($admin)
            ->post('/org/personnel', ['name' => 'Maria Bayang', 'email' => $email, 'role' => $role])
            ->assertSessionHasNoErrors();

        return OrganizationMember::whereHas('user', fn ($q) => $q->where('email', $email))->sole();
    }

    public function test_an_administrator_can_invite_personnel()
    {
        Notification::fake();
        ['organization' => $organization, 'admin' => $admin] = $this->organization();

        $member = $this->invite($admin);

        $this->assertSame($organization->id, $member->organization_id);
        $this->assertSame(OrganizationRole::Reviewer, $member->role);
        $this->assertSame($admin->id, $member->invited_by);
        $this->assertSame(AccountType::Organization, $member->user->account_type);
        $this->assertNull($member->user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'personnel.invited', 'organization_id' => $organization->id]);
        Notification::assertSentTo($member->user, OrganizationInvitation::class);

        $this->get('/org/personnel')
            ->assertInertia(fn ($page) => $page
                ->component('organization/personnel')
                ->has('members', 2)
                ->where('members.1.invitation_pending', true)
                ->has('roles', 3));
    }

    public function test_an_invitation_sets_the_password_verifies_the_email_and_signs_in()
    {
        Notification::fake();
        ['admin' => $admin] = $this->organization();
        $member = $this->invite($admin);
        Auth::guard('web')->logout();

        $url = OrganizationInvitation::url($member);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/accept-invitation')->where('email', 'maria@coop.test'));

        $this->post($url, ['password' => 'a-strong-passphrase', 'password_confirmation' => 'a-strong-passphrase'])
            ->assertRedirect(route('org.dashboard'));

        $user = $member->user->refresh();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('a-strong-passphrase', $user->password));
        $this->assertNotNull($user->email_verified_at);

        // The link works only once.
        Auth::guard('web')->logout();
        $this->get($url)->assertStatus(410);
    }

    public function test_invitation_links_must_be_signed()
    {
        Notification::fake();
        ['admin' => $admin] = $this->organization();
        $member = $this->invite($admin);
        Auth::guard('web')->logout();

        $this->get("/invitations/{$member->user_id}")->assertForbidden();
        $this->get(OrganizationInvitation::url($member).'x')->assertForbidden();

        // Self-registered administrators were never invited.
        $this->get(OrganizationInvitation::url($admin->organizationMembership))->assertStatus(410);
    }

    public function test_existing_accounts_cannot_be_invited()
    {
        ['admin' => $admin] = $this->organization();
        $seeker = User::factory()->seeker()->create();

        $this->actingAs($admin)
            ->post('/org/personnel', ['name' => 'Someone', 'email' => $seeker->email, 'role' => 'reviewer'])
            ->assertSessionHasErrors('email');
    }

    public function test_only_administrators_manage_personnel()
    {
        ['organization' => $organization] = $this->organization();
        $officer = User::factory()->organization()->create();
        $organization->members()->create(['user_id' => $officer->id, 'role' => OrganizationRole::RecruitmentOfficer, 'is_active' => true]);

        $this->actingAs($officer)->get('/org/personnel')->assertForbidden();
        $this->post('/org/personnel', ['name' => 'X', 'email' => 'x@coop.test', 'role' => 'admin'])->assertForbidden();

        $this->get('/org/dashboard')->assertInertia(fn ($page) => $page
            ->where('auth.organization.role', 'recruitment_officer')
            ->where('auth.organization.permissions', fn ($permissions) => ! collect($permissions)->contains('manage_personnel')));
    }

    public function test_roles_can_be_changed_and_members_deactivated()
    {
        Notification::fake();
        ['admin' => $admin] = $this->organization();
        $member = $this->invite($admin);

        $this->patch("/org/personnel/{$member->id}", ['role' => 'recruitment_officer', 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame(OrganizationRole::RecruitmentOfficer, $member->refresh()->role);

        $this->patch("/org/personnel/{$member->id}", ['role' => 'recruitment_officer', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($member->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'personnel.updated', 'auditable_id' => $member->id]);

        $member->user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($member->user->refresh())->get('/org/dashboard')->assertForbidden();
    }

    public function test_the_last_administrator_and_ones_own_access_are_protected()
    {
        Notification::fake();
        ['admin' => $admin] = $this->organization();
        $member = $this->invite($admin, role: 'admin');

        $this->patch("/org/personnel/{$admin->organizationMembership->id}", ['role' => 'reviewer', 'is_active' => true])
            ->assertSessionHasErrors('member');

        // With two administrators one can step down; the last one cannot.
        $this->patch("/org/personnel/{$member->id}", ['role' => 'reviewer', 'is_active' => true])->assertSessionHasNoErrors();

        $second = User::factory()->organization()->create();
        $admin->organizationMembership->organization->members()->create(['user_id' => $second->id, 'role' => OrganizationRole::Reviewer, 'is_active' => true]);
        $this->actingAs($admin)->patch("/org/personnel/{$admin->organizationMembership->id}", ['role' => 'admin', 'is_active' => true])->assertSessionHasNoErrors();

        $this->assertSame(OrganizationRole::Admin, $admin->organizationMembership->refresh()->role);
    }

    public function test_invitations_can_be_resent_until_accepted()
    {
        Notification::fake();
        ['admin' => $admin] = $this->organization();
        $member = $this->invite($admin);

        $this->post("/org/personnel/{$member->id}/resend")->assertSessionHasNoErrors();
        Notification::assertSentToTimes($member->user, OrganizationInvitation::class, 2);

        $this->post("/org/personnel/{$admin->organizationMembership->id}/resend")->assertSessionHasErrors('member');
    }

    public function test_members_of_other_organizations_cannot_be_edited()
    {
        ['admin' => $admin] = $this->organization();
        ['admin' => $otherAdmin] = $this->organization();

        $this->actingAs($admin)
            ->patch("/org/personnel/{$otherAdmin->organizationMembership->id}", ['role' => 'reviewer', 'is_active' => false])
            ->assertNotFound();

        $this->assertTrue($otherAdmin->organizationMembership->refresh()->is_active);
    }
}
