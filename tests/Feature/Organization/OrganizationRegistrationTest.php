<?php

namespace Tests\Feature\Organization;

use App\Enums\AccountType;
use App\Enums\OrganizationRole;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private LocalFilesystemAdapter $private;

    protected function setUp(): void
    {
        parent::setUp();

        $this->private = Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return [
            'name' => 'Bauko Highland Farmers Cooperative',
            'type' => 'ngo',
            'official_email' => 'office@bhfc.example',
            'contact_number' => '0917 555 1234',
            'office_address' => 'Poblacion, Bauko, Mountain Province',
            'description' => 'A farmers cooperative supporting highland vegetable growers.',
            'logo' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
            'documents' => [UploadedFile::fake()->create('cda-registration.pdf', 300, 'application/pdf')],
            'representative_name' => 'Pedro Bayang',
            'representative_position' => 'Chairperson',
            'email' => 'pedro@bhfc.example',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_consent' => true,
            ...$overrides,
        ];
    }

    public function test_registration_page_renders()
    {
        $this->get('/organizations/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/register-organization')->has('organizationTypes', 6));
    }

    public function test_an_organization_can_register_and_awaits_verification()
    {
        $response = $this->post('/organizations/register', $this->validData());

        $response->assertRedirect('/org/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'pedro@bhfc.example')->firstOrFail();
        $organization = Organization::sole();

        $this->assertSame(AccountType::Organization, $user->account_type);
        $this->assertSame(VerificationStatus::Pending, $organization->verification_status);
        $this->assertSame(OrganizationRole::Admin, $user->organizationMembership->role);
        $this->assertSame($organization->id, $user->organizationMembership->organization_id);

        $document = $organization->documents()->sole();
        $this->private->assertExists($document->disk_path);
        $this->private->assertExists($organization->logo_path);

        $this->assertTrue(AuditLog::where('action', 'organization.registered')->exists());
    }

    public function test_logos_are_served_publicly_from_the_uploads_disk()
    {
        $this->post('/organizations/register', $this->validData());
        $organization = Organization::sole();
        Auth::guard('web')->logout();

        $url = $organization->logoUrl();
        $this->assertStringContainsString("/organizations/{$organization->id}/logo?v=", $url);

        $response = $this->get($url)->assertOk();
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));

        // Still cacheable for signed-in visitors, unlike private pages.
        $seeker = User::factory()->create();
        $this->actingAs($seeker)->get($url)->assertOk()->assertHeaderMissing('Pragma');

        $organization->update(['logo_path' => null]);
        $this->get("/organizations/{$organization->id}/logo")->assertNotFound();
    }

    public function test_at_least_one_verification_document_is_required()
    {
        $this->post('/organizations/register', $this->validData(['documents' => []]))
            ->assertSessionHasErrors('documents');

        $this->assertSame(0, Organization::count());
    }

    public function test_documents_must_be_pdf_or_images()
    {
        $this->post('/organizations/register', $this->validData([
            'documents' => [UploadedFile::fake()->create('permit.exe', 10, 'application/x-msdownload')],
        ]))->assertSessionHasErrors('documents.0');
    }

    public function test_privacy_consent_is_required()
    {
        $this->post('/organizations/register', $this->validData(['privacy_consent' => false]))
            ->assertSessionHasErrors('privacy_consent');
    }

    public function test_pending_organizations_can_open_their_dashboard()
    {
        $this->post('/organizations/register', $this->validData());
        $user = Auth::user();
        $this->assertInstanceOf(User::class, $user);
        $user->markEmailAsVerified();

        $this->get('/org/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('organization/dashboard')
                ->where('organization.verification_status', 'pending'));

        $this->get('/dashboard')->assertRedirect('/org/dashboard');
    }

    public function test_seekers_cannot_open_the_organization_area()
    {
        $this->actingAs(User::factory()->seeker()->create())->get('/org/dashboard')->assertForbidden();
    }

    public function test_a_rejected_organization_can_resubmit_by_updating_details()
    {
        $organization = Organization::factory()->withMember()->create([
            'verification_status' => VerificationStatus::Rejected,
            'status_reason' => 'Business permit is expired.',
        ]);
        $admin = $organization->members()->sole()->user;

        $this->actingAs($admin)->post('/org/profile', [
            'name' => $organization->name,
            'type' => $organization->type->value,
            'official_email' => $organization->official_email,
            'contact_number' => '0917 555 1234',
            'office_address' => $organization->office_address,
            'representative_name' => $organization->representative_name,
            'documents' => [UploadedFile::fake()->create('renewed-permit.pdf', 200, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(VerificationStatus::Pending, $organization->fresh()->verification_status);
        $this->assertSame(1, $organization->documents()->count());
        $this->assertTrue(AuditLog::where('action', 'organization.resubmitted')->exists());
    }

    public function test_only_organization_administrators_can_edit_the_organization()
    {
        $organization = Organization::factory()->withMember(OrganizationRole::Reviewer)->create();
        $reviewer = $organization->members()->sole()->user;

        $this->actingAs($reviewer)->post('/org/profile', [
            'name' => 'Renamed',
            'type' => 'ngo',
            'official_email' => 'x@example.com',
            'contact_number' => '0917 555 1234',
            'office_address' => 'Somewhere',
            'representative_name' => 'Someone',
        ])->assertForbidden();
    }
}
