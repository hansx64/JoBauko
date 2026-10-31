<?php

namespace Tests\Feature\Admin;

use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationVerificationUpdated;
use App\Services\Organizations\OrganizationRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admins_see_pending_organizations_first()
    {
        Organization::factory()->verified()->create(['name' => 'Verified Org']);
        Organization::factory()->create(['name' => 'Pending Org']);

        $this->actingAs($this->admin())
            ->get('/admin/organizations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/organizations/index')
                ->where('organizations.data.0.name', 'Pending Org'));
    }

    public function test_admins_can_review_an_organization()
    {
        $organization = Organization::factory()->withMember()->create();

        $this->actingAs($this->admin())
            ->get("/admin/organizations/{$organization->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/organizations/show')->has('members', 1));
    }

    public function test_approving_verifies_audits_and_notifies()
    {
        Notification::fake();
        $organization = Organization::factory()->withMember()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/organizations/{$organization->id}/approve")->assertSessionHasNoErrors();

        $organization->refresh();
        $this->assertSame(VerificationStatus::Verified, $organization->verification_status);
        $this->assertSame($admin->id, $organization->verified_by);

        $log = AuditLog::where('action', 'organization.verified')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('pending', $log->old_values['verification_status']);

        Notification::assertSentTo($organization->members()->sole()->user, OrganizationVerificationUpdated::class);
    }

    public function test_rejecting_requires_a_reason()
    {
        $organization = Organization::factory()->create();

        $this->actingAs($this->admin())
            ->post("/admin/organizations/{$organization->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(VerificationStatus::Pending, $organization->fresh()->verification_status);
    }

    public function test_rejecting_records_the_reason()
    {
        Notification::fake();
        $organization = Organization::factory()->withMember()->create();

        $this->actingAs($this->admin())
            ->post("/admin/organizations/{$organization->id}/reject", ['reason' => 'The business permit is expired.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(VerificationStatus::Rejected, $organization->fresh()->verification_status);
        $this->assertSame('The business permit is expired.', $organization->fresh()->status_reason);
    }

    public function test_suspend_and_reinstate()
    {
        Notification::fake();
        $organization = Organization::factory()->verified()->create();
        $this->actingAs($this->admin());

        $this->post("/admin/organizations/{$organization->id}/suspend", ['reason' => 'Fraudulent listings were reported.'])->assertSessionHasNoErrors();
        $this->assertSame(VerificationStatus::Suspended, $organization->fresh()->verification_status);

        $this->post("/admin/organizations/{$organization->id}/reinstate")->assertSessionHasNoErrors();
        $this->assertSame(VerificationStatus::Verified, $organization->fresh()->verification_status);

        $this->assertSame(['organization.suspended', 'organization.reinstated'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_invalid_transitions_are_refused()
    {
        $organization = Organization::factory()->verified()->create();

        $this->actingAs($this->admin())
            ->post("/admin/organizations/{$organization->id}/reject", ['reason' => 'Changed my mind about this.'])
            ->assertSessionHasErrors('status');
    }

    public function test_non_admins_cannot_decide()
    {
        $organization = Organization::factory()->withMember()->create();
        $member = $organization->members()->sole()->user;

        $this->actingAs($member)->post("/admin/organizations/{$organization->id}/approve")->assertForbidden();
        $this->actingAs(User::factory()->seeker()->create())->get('/admin/organizations')->assertForbidden();

        $this->assertSame(VerificationStatus::Pending, $organization->fresh()->verification_status);
    }

    public function test_verification_documents_are_only_visible_to_admins_and_the_organization()
    {
        Storage::fake('local');
        $organization = Organization::factory()->withMember()->create();
        $other = Organization::factory()->withMember()->create();
        app(OrganizationRegistrationService::class)
            ->storeDocument($organization, UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf'), 'verification');
        $document = $organization->documents()->sole();

        $this->actingAs($this->admin())->get("/organization-documents/{$document->id}")->assertOk();
        $this->actingAs($organization->members()->sole()->user)->get("/organization-documents/{$document->id}")->assertOk();
        $this->actingAs($other->members()->sole()->user)->get("/organization-documents/{$document->id}")->assertForbidden();
        $this->actingAs(User::factory()->seeker()->create())->get("/organization-documents/{$document->id}")->assertForbidden();
    }
}
