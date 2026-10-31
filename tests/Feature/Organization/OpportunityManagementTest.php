<?php

namespace Tests\Feature\Organization;

use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityManagementTest extends TestCase
{
    use RefreshDatabase;

    private function member(?Organization $organization = null, OrganizationRole $role = OrganizationRole::Admin): User
    {
        $organization ??= Organization::factory()->verified()->create();
        $user = User::factory()->organization()->create();
        $organization->members()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => true]);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function jobData(array $overrides = []): array
    {
        return [
            'category' => 'job',
            'title' => 'Municipal IT Assistant',
            'description' => 'Maintain computers and the network of the municipal hall.',
            'qualifications' => 'Graduate of an IT-related course.',
            'min_education' => 'college_graduate',
            'location_text' => 'Municipal Hall, Poblacion',
            'is_remote' => false,
            'application_deadline' => now()->addWeeks(3)->toDateString(),
            'slots' => 1,
            'requirements' => ['Resume', 'Transcript of records'],
            'documents' => [['name' => 'Resume', 'is_required' => true], ['name' => 'Transcript of Records', 'is_required' => false]],
            'skills' => ['Networking', 'Technical Support'],
            'job' => ['employment_type' => 'contractual', 'salary_min' => 18000, 'salary_max' => 22000, 'work_setup' => 'onsite', 'min_experience_years' => 0],
            ...$overrides,
        ];
    }

    public function test_an_incomplete_draft_can_be_saved()
    {
        $this->actingAs($this->member())
            ->post('/org/opportunities', ['category' => 'livelihood', 'title' => 'Weaving starter kits', 'intent' => 'draft'])
            ->assertSessionHasNoErrors();

        $opportunity = Opportunity::sole();
        $this->assertSame(OpportunityStatus::Draft, $opportunity->status);
        $this->assertNull($opportunity->livelihoodDetail);
        $this->assertTrue(AuditLog::where('action', 'opportunity.created')->exists());
    }

    public function test_a_verified_organization_can_create_and_publish_a_job()
    {
        $this->actingAs($this->member())
            ->post('/org/opportunities', $this->jobData(['intent' => 'publish']))
            ->assertRedirect('/org/opportunities');

        $opportunity = Opportunity::sole();
        $this->assertSame(OpportunityStatus::Published, $opportunity->status);
        $this->assertNotNull($opportunity->published_at);
        $this->assertSame('contractual', $opportunity->jobDetail->employment_type->value);
        $this->assertSame(['Networking', 'Technical Support'], $opportunity->skills->pluck('name')->all());
        $this->assertSame(2, $opportunity->requiredDocuments()->count());
        $this->assertSame(2, $opportunity->requirements()->count());
    }

    public function test_publishing_requires_category_details()
    {
        $this->actingAs($this->member())
            ->post('/org/opportunities', $this->jobData(['intent' => 'publish', 'job' => ['employment_type' => null]]))
            ->assertSessionHasErrors('job.employment_type');

        $this->assertSame(0, Opportunity::count());
    }

    public function test_livelihood_programs_require_beneficiaries_and_assistance_when_published()
    {
        $this->actingAs($this->member())
            ->post('/org/opportunities', [
                'intent' => 'publish',
                'category' => 'livelihood',
                'title' => 'Highland vegetable starter kits',
                'description' => 'Seeds and tools for new growers.',
                'location_text' => 'Bauko',
                'application_deadline' => now()->addWeek()->toDateString(),
                'livelihood' => ['assistance_type' => 'farm_inputs'],
            ])
            ->assertSessionHasErrors(['livelihood.target_beneficiaries', 'livelihood.assistance_provided']);
    }

    public function test_a_pending_organization_keeps_the_draft_when_publishing_is_refused()
    {
        $user = $this->member(Organization::factory()->create());

        $response = $this->actingAs($user)->post('/org/opportunities', $this->jobData(['intent' => 'publish']));

        $opportunity = Opportunity::sole();
        $response->assertRedirect("/org/opportunities/{$opportunity->id}/edit")->assertSessionHasErrors('status');
        $this->assertSame(OpportunityStatus::Draft, $opportunity->status);
    }

    public function test_organizations_cannot_touch_other_organizations_opportunities()
    {
        $opportunity = Opportunity::factory()->job()->create();
        $this->actingAs($this->member());

        $this->get("/org/opportunities/{$opportunity->id}/edit")->assertNotFound();
        $this->patch("/org/opportunities/{$opportunity->id}", $this->jobData())->assertNotFound();
        $this->post("/org/opportunities/{$opportunity->id}/publish")->assertNotFound();
        $this->post("/org/opportunities/{$opportunity->id}/archive")->assertNotFound();

        $this->assertSame(OpportunityStatus::Draft, $opportunity->fresh()->status);
    }

    public function test_the_list_only_shows_the_organizations_own_opportunities()
    {
        $user = $this->member();
        Opportunity::factory()->create(['organization_id' => $user->organizationMembership->organization_id, 'title' => 'Ours']);
        Opportunity::factory()->create(['title' => 'Theirs']);

        $this->actingAs($user)
            ->get('/org/opportunities')
            ->assertInertia(fn ($page) => $page->has('opportunities.data', 1)->where('opportunities.data.0.title', 'Ours'));
    }

    public function test_reviewers_cannot_manage_opportunities()
    {
        $organization = Organization::factory()->verified()->create();
        $reviewer = $this->member($organization, OrganizationRole::Reviewer);
        $opportunity = Opportunity::factory()->job()->create(['organization_id' => $organization->id]);

        $this->actingAs($reviewer)->get('/org/opportunities/create')->assertForbidden();
        $this->actingAs($reviewer)->post("/org/opportunities/{$opportunity->id}/publish")->assertForbidden();
    }

    public function test_suspended_organizations_cannot_manage_opportunities()
    {
        $user = $this->member(Organization::factory()->suspended()->create());

        $this->actingAs($user)->post('/org/opportunities', $this->jobData())->assertForbidden();
    }

    public function test_lifecycle_publish_close_reopen_archive()
    {
        $user = $this->member();
        $opportunity = Opportunity::factory()->job()->create(['organization_id' => $user->organizationMembership->organization_id]);
        $this->actingAs($user);

        $this->post("/org/opportunities/{$opportunity->id}/publish")->assertSessionHasNoErrors();
        $this->assertSame(OpportunityStatus::Published, $opportunity->fresh()->status);

        $this->post("/org/opportunities/{$opportunity->id}/close")->assertSessionHasNoErrors();
        $this->assertSame(OpportunityStatus::Closed, $opportunity->fresh()->status);

        $newDeadline = now()->addWeeks(2)->toDateString();
        $this->post("/org/opportunities/{$opportunity->id}/reopen", ['application_deadline' => $newDeadline])->assertSessionHasNoErrors();
        $this->assertSame(OpportunityStatus::Published, $opportunity->fresh()->status);
        $this->assertSame($newDeadline, $opportunity->fresh()->application_deadline->toDateString());
        $this->assertNotNull($opportunity->fresh()->reopened_at);

        $this->post("/org/opportunities/{$opportunity->id}/archive")->assertSessionHasNoErrors();
        $this->assertSame(OpportunityStatus::Archived, $opportunity->fresh()->status);

        $this->assertSame(
            ['opportunity.published', 'opportunity.closed', 'opportunity.reopened', 'opportunity.archived'],
            AuditLog::orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_archived_opportunities_cannot_be_edited_or_published()
    {
        $user = $this->member();
        $opportunity = Opportunity::factory()->job()->create([
            'organization_id' => $user->organizationMembership->organization_id,
            'status' => OpportunityStatus::Archived,
        ]);
        $this->actingAs($user);

        $this->patch("/org/opportunities/{$opportunity->id}", $this->jobData())->assertSessionHasErrors('status');
        $this->post("/org/opportunities/{$opportunity->id}/publish")->assertSessionHasErrors('status');
    }

    public function test_extending_requires_a_later_deadline()
    {
        $user = $this->member();
        $opportunity = Opportunity::factory()->job()->published()->create([
            'organization_id' => $user->organizationMembership->organization_id,
            'application_deadline' => now()->addWeek()->toDateString(),
        ]);
        $this->actingAs($user);

        $this->post("/org/opportunities/{$opportunity->id}/extend", ['application_deadline' => now()->addDays(3)->toDateString()])
            ->assertSessionHasErrors('application_deadline');

        $later = now()->addWeeks(3)->toDateString();
        $this->post("/org/opportunities/{$opportunity->id}/extend", ['application_deadline' => $later])->assertSessionHasNoErrors();
        $this->assertSame($later, $opportunity->fresh()->application_deadline->toDateString());
    }

    public function test_expired_opportunities_show_as_expired_and_can_be_reopened()
    {
        $user = $this->member();
        $opportunity = Opportunity::factory()->job()->expired()->create(['organization_id' => $user->organizationMembership->organization_id]);
        $this->actingAs($user);

        $this->assertSame('expired', $opportunity->displayStatus());
        $this->assertFalse($opportunity->isAcceptingApplications());

        $this->get('/org/opportunities?status=expired')->assertInertia(fn ($page) => $page->has('opportunities.data', 1));

        $this->post("/org/opportunities/{$opportunity->id}/extend", ['application_deadline' => now()->addWeek()->toDateString()])
            ->assertSessionHasErrors('status');

        $this->post("/org/opportunities/{$opportunity->id}/reopen", ['application_deadline' => now()->addWeek()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertTrue($opportunity->fresh()->isAcceptingApplications());
    }

    public function test_editing_keeps_required_document_ids_and_category_is_locked_after_publishing()
    {
        $user = $this->member();
        $this->actingAs($user)->post('/org/opportunities', $this->jobData(['intent' => 'publish']));
        $opportunity = Opportunity::sole();
        $resume = $opportunity->requiredDocuments()->where('name', 'Resume')->sole();

        $this->patch("/org/opportunities/{$opportunity->id}", $this->jobData([
            'title' => 'Municipal IT Assistant II',
            'documents' => [['id' => $resume->id, 'name' => 'Updated resume', 'is_required' => true]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Municipal IT Assistant II', $opportunity->fresh()->title);
        $this->assertSame([$resume->id], $opportunity->requiredDocuments()->pluck('id')->all());
        $this->assertSame('Updated resume', $resume->fresh()->name);

        $this->patch("/org/opportunities/{$opportunity->id}", $this->jobData(['category' => 'scholarship', 'scholarship' => ['education_level' => 'college_level']]))
            ->assertSessionHasErrors('category');
        $this->assertSame(OpportunityCategory::Job, $opportunity->fresh()->category);
    }

    public function test_deadline_must_not_be_in_the_past_when_publishing()
    {
        $this->actingAs($this->member())
            ->post('/org/opportunities', $this->jobData(['intent' => 'publish', 'application_deadline' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('application_deadline');
    }
}
