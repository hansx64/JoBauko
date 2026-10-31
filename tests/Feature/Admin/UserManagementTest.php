<?php

namespace Tests\Feature\Admin;

use App\Enums\Classification;
use App\Enums\OrganizationRole;
use App\Models\Application;
use App\Models\Barangay;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccountStatusChanged;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_list_and_filter_users()
    {
        $this->seed(LocationSeeder::class);
        $admin = User::factory()->admin()->create();
        $barangay = Barangay::where('name', 'Abatan')->sole();
        $student = User::factory()->seeker()->create(['name' => 'Ana Student', 'barangay_id' => $barangay->id]);
        $student->applicantProfile->update(['classification' => Classification::Student]);
        User::factory()->seeker()->create(['name' => 'Ben Seeker'])->applicantProfile->update(['classification' => Classification::Employed]);
        User::factory()->organization()->inactive()->create(['name' => 'Cora Staff']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/index')
                ->has('users.data', 4)
                ->where('counts.seeker', 2)
                ->has('options.barangays', 22));

        $this->get('/admin/users?type=seeker&classification=student')
            ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.name', 'Ana Student'));

        $this->get("/admin/users?barangay={$barangay->id}")
            ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.barangay', 'Abatan'));

        $this->get('/admin/users?status=inactive')
            ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.name', 'Cora Staff'));

        $this->get('/admin/users?q=ben')
            ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.name', 'Ben Seeker'));
    }

    public function test_viewing_a_user_shows_a_summary_and_is_logged()
    {
        $admin = User::factory()->admin()->create();
        $seeker = User::factory()->seeker()->create();
        Application::factory()->for($seeker)->create();

        $this->actingAs($admin)
            ->get("/admin/users/{$seeker->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/show')
                ->where('account.email', $seeker->email)
                ->where('seeker.applications_total', 1)
                ->has('seeker.recent_applications', 1)
                ->missing('seeker.recent_applications.0.cover_letter')
                ->where('membership', null)
                ->where('isSelf', false));

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.viewed', 'auditable_id' => $seeker->id, 'user_id' => $admin->id]);
    }

    public function test_deactivating_signs_the_user_out_and_records_the_reason()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember(OrganizationRole::Admin, $staff)->create();
        DB::table('sessions')->insert(['id' => 'session-1', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)
            ->post("/admin/users/{$staff->id}/deactivate", ['reason' => 'Posted fake job offers asking for fees.'])
            ->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertFalse($staff->is_active);
        $this->assertSame('Posted fake job offers asking for fees.', $staff->deactivation_reason);
        $this->assertSame($admin->id, $staff->deactivated_by);
        $this->assertDatabaseMissing('sessions', ['user_id' => $staff->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'auditable_id' => $staff->id, 'organization_id' => $organization->id]);
        Notification::assertSentTo($staff, AccountStatusChanged::class, fn ($n) => $n->active === false);

        // A session that survived is ended on the next request.
        $this->actingAs($staff)->get('/org/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_reason_is_required_and_admins_cannot_deactivate_themselves()
    {
        $admin = User::factory()->admin()->create();
        $seeker = User::factory()->seeker()->create();

        $this->actingAs($admin)->post("/admin/users/{$seeker->id}/deactivate", ['reason' => 'spam'])->assertSessionHasErrors('reason');
        $this->post("/admin/users/{$admin->id}/deactivate", ['reason' => 'Testing self deactivation.'])->assertSessionHasErrors('account');

        $this->assertTrue($seeker->refresh()->is_active);
        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_deactivated_accounts_can_be_reactivated()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $seeker = User::factory()->seeker()->inactive()->create();

        $this->actingAs($admin)->post("/admin/users/{$seeker->id}/reactivate")->assertSessionHasNoErrors();

        $seeker->refresh();
        $this->assertTrue($seeker->is_active);
        $this->assertNull($seeker->deactivation_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.reactivated', 'auditable_id' => $seeker->id]);
        Notification::assertSentTo($seeker, AccountStatusChanged::class, fn ($n) => $n->active === true);

        $this->post("/admin/users/{$seeker->id}/reactivate")->assertSessionHasErrors('account');
    }

    public function test_only_admins_manage_users()
    {
        $seeker = User::factory()->seeker()->create();
        $other = User::factory()->seeker()->create();

        $this->actingAs($seeker)->get('/admin/users')->assertForbidden();
        $this->post("/admin/users/{$other->id}/deactivate", ['reason' => 'Trying to lock someone out.'])->assertForbidden();

        $this->assertTrue($other->refresh()->is_active);
    }
}
