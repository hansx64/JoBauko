<?php

namespace Tests\Feature\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\Classification;
use App\Enums\Gender;
use App\Models\Application;
use App\Models\Barangay;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Reports\ReportCatalog;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LocationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * A small, known data set inside the default period (this year).
     *
     * @return array{organization: Organization, job: Opportunity, approved: Application, seeker: User}
     */
    private function data(): array
    {
        $barangay = Barangay::where('name', 'Abatan')->sole();
        $organization = Organization::factory()->verified()->create(['name' => 'Bauko Farmers Cooperative']);
        $job = Opportunity::factory()->job()->published()->for($organization)->create(['title' => 'Farm Technician', 'barangay_id' => $barangay->id, 'slots' => 3]);

        $seeker = User::factory()->seeker()->create(['name' => 'Ana Bayang', 'barangay_id' => $barangay->id]);
        $seeker->applicantProfile->update(['classification' => Classification::Unemployed, 'gender' => Gender::Female, 'date_of_birth' => now()->subYears(22)]);

        $approved = Application::factory()->for($job)->for($seeker)->status(ApplicationStatus::Approved)->create([
            'submitted_at' => now()->subDays(4),
            'first_reviewed_at' => now()->subDays(2),
            'decided_at' => now()->subDay(),
        ]);
        Application::factory()->for($job)->status(ApplicationStatus::Rejected)->create();
        Application::factory()->for($job)->create();

        return ['organization' => $organization, 'job' => $job, 'approved' => $approved, 'seeker' => $seeker];
    }

    public function test_the_index_lists_every_report()
    {
        $this->actingAs($this->admin)
            ->get('/admin/analytics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/analytics/index')
                ->has('reports', 8)
                ->where('reports.4.key', 'approved-applicants')
                ->where('reports.4.personal_data', true));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reportKeys(): array
    {
        return [
            'registered users' => ['registered-users'],
            'organizations' => ['organizations'],
            'published opportunities' => ['published-opportunities'],
            'applications' => ['applications'],
            'approved applicants' => ['approved-applicants'],
            'categories' => ['opportunity-categories'],
            'geography' => ['geographic-distribution'],
            'organization activity' => ['organization-activity'],
        ];
    }

    #[DataProvider('reportKeys')]
    public function test_each_report_renders_and_exports(string $key)
    {
        $this->data();

        $this->actingAs($this->admin)
            ->get("/admin/analytics/{$key}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/analytics/show')->where('report.key', $key)->has('summary')->has('tables'));

        $pdf = $this->get("/admin/analytics/{$key}/export/pdf");
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get("/admin/analytics/{$key}/export/xlsx")->assertOk()->assertDownload();
    }

    public function test_figures_are_correct()
    {
        $this->data();
        $this->actingAs($this->admin);

        $this->get('/admin/analytics/applications')->assertInertia(fn ($page) => $page
            ->where('summary.0', ['label' => 'Applications submitted', 'value' => 3])
            ->where('summary.1.value', 1)
            ->where('summary.2.value', '50%')
            // 48 hours for the approved one, none for the factory-made rejected one.
            ->where('summary.3.value', '1 day'));

        $this->get('/admin/analytics/approved-applicants')->assertInertia(fn ($page) => $page
            ->where('tables.0.total_rows', 1)
            ->where('tables.0.rows.0.name', 'Ana Bayang')
            ->where('tables.0.rows.0.barangay', 'Abatan')
            ->where('tables.0.rows.0.classification', 'Unemployed'));

        $this->get('/admin/analytics/geographic-distribution')->assertInertia(fn ($page) => $page
            // 22 barangays, plus applicants made by the factory without one.
            ->has('tables.0.rows', 23)
            ->where('tables.0.rows.22.barangay', 'Not stated')
            ->where('tables.0.rows', fn ($rows) => collect($rows)->firstWhere('barangay', 'Abatan')['opportunities'] === 1
                && collect($rows)->firstWhere('barangay', 'Abatan')['approved'] === 1));

        $this->get('/admin/analytics/registered-users')->assertInertia(fn ($page) => $page
            // Other factory-made seekers have random profiles, so only a floor is certain.
            ->where('tables.1.rows', fn ($rows) => collect($rows)->firstWhere('classification', 'Unemployed')['female'] >= 1)
            ->where('tables.2.rows.1.group', '18–24')
            ->where('tables.2.rows.1.total', fn ($total) => $total >= 1));

        $this->get('/admin/analytics/organization-activity')->assertInertia(fn ($page) => $page
            ->where('tables.0.rows.0.organization', 'Bauko Farmers Cooperative')
            ->where('tables.0.rows.0.received', 3)
            ->where('tables.0.rows.0.approved', 1));
    }

    public function test_the_period_limits_what_is_counted()
    {
        $this->data();

        $this->actingAs($this->admin)
            ->get('/admin/analytics/applications?from=2020-01-01&to=2020-12-31')
            ->assertInertia(fn ($page) => $page
                ->where('period.from', '2020-01-01')
                ->where('summary.0.value', 0));

        $this->get('/admin/analytics/applications?from=2026-05-01&to=2026-01-01')->assertSessionHasErrors('to');
        $this->get('/admin/analytics/applications?from=yesterday')->assertSessionHasErrors('from');
    }

    public function test_personal_data_reports_and_all_exports_are_audited()
    {
        $this->data();
        $this->actingAs($this->admin);

        $this->get('/admin/analytics/applications');
        $this->assertDatabaseMissing('audit_logs', ['action' => 'report.viewed']);

        $this->get('/admin/analytics/approved-applicants');
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.viewed', 'user_id' => $this->admin->id]);

        $this->get('/admin/analytics/applications/export/xlsx');
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported', 'user_id' => $this->admin->id]);
    }

    public function test_the_spreadsheet_holds_every_table_and_keeps_text_as_text()
    {
        ['organization' => $organization] = $this->data();
        $organization->update(['name' => '=HYPERLINK("http://evil.example","Click")']);

        $response = $this->actingAs($this->admin)->get('/admin/analytics/organization-activity/export/xlsx');
        /** @var BinaryFileResponse $download */
        $download = $response->baseResponse;
        $book = IOFactory::load($download->getFile()->getPathname());

        $this->assertSame(['Summary', 'By organization'], $book->getSheetNames());
        $this->assertSame('Organization activity', $book->getSheet(0)->getCell('A1')->getValue());

        $sheet = $book->getSheetByName('By organization');
        $this->assertSame('Organization', $sheet->getCell('A1')->getValue());
        $this->assertSame('=HYPERLINK("http://evil.example","Click")', $sheet->getCell('A2')->getValue());
        $this->assertSame('s', $sheet->getCell('A2')->getDataType());
        $this->assertSame(3, $sheet->getCell('E2')->getValue());
    }

    public function test_access_is_limited_to_admins_and_known_reports()
    {
        $this->actingAs($this->admin)->get('/admin/analytics/not-a-report')->assertNotFound();
        $this->get('/admin/analytics/applications/export/csv')->assertNotFound();

        $seeker = User::factory()->seeker()->create();
        $this->actingAs($seeker)->get('/admin/analytics')->assertForbidden();
        $this->get('/admin/analytics/approved-applicants/export/xlsx')->assertForbidden();

        $this->assertCount(8, ReportCatalog::all());
    }
}
