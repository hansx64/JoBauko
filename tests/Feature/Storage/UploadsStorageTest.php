<?php

namespace Tests\Feature\Storage;

use App\Enums\ResumeExtractionStatus;
use App\Models\User;
use App\Support\EnvFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Masbug\Flysystem\GoogleDriveAdapter;
use Tests\TestCase;

class UploadsStorageTest extends TestCase
{
    use RefreshDatabase;

    private function googleConfig(array $overrides = []): void
    {
        config(['filesystems.disks.google' => array_merge([
            'driver' => 'google',
            'client_id' => 'client-id.apps.googleusercontent.com',
            'client_secret' => 'client-secret',
            'refresh_token' => 'refresh-token',
            'folder_id' => 'folder-id',
            'throw' => true,
        ], $overrides)]);
        Storage::forgetDisk('google');
    }

    public function test_the_google_disk_is_built_without_contacting_google()
    {
        $this->googleConfig();

        // Fake credentials: any call to Google here would throw.
        $disk = Storage::disk('google');

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertInstanceOf(GoogleDriveAdapter::class, $disk->getAdapter());
    }

    public function test_the_google_disk_explains_missing_settings()
    {
        $this->googleConfig(['refresh_token' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GOOGLE_DRIVE_REFRESH_TOKEN is missing. Run php artisan jobauko:google-drive.');

        Storage::disk('google');
    }

    public function test_uploads_are_stored_read_and_served_from_the_configured_disk()
    {
        config(['jobauko.uploads_disk' => 'remote']);
        $remote = Storage::fake('remote');
        $local = Storage::fake('local');
        $seeker = User::factory()->seeker()->create();

        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, Pdf::loadHTML('<p>MARIA SANTOS</p><p>EDUCATION</p><p>Bachelor of Science in Agriculture</p><p>Benguet State University</p>')->output());

        $this->actingAs($seeker)
            ->post('/resume', ['resume' => new UploadedFile($path, 'maria.pdf', null, null, true)])
            ->assertSessionHasNoErrors();

        $resume = $seeker->fresh()->currentResume;
        $remote->assertExists($resume->disk_path);
        $this->assertSame([], $local->allFiles());
        // The text was read from the remote disk.
        $this->assertSame(ResumeExtractionStatus::Extracted, $resume->extraction_status);

        $this->get(route('seeker.resume.file', $resume))->assertOk()->assertDownload('maria.pdf');
    }

    public function test_existing_uploads_can_be_copied_to_another_disk()
    {
        $local = Storage::fake('local');
        $google = Storage::fake('google');
        $local->put('resumes/1/a.pdf', 'resume');
        $local->put('applications/2/b.pdf', 'document');
        $local->put('organization-documents/3/c.pdf', 'permit');
        $local->put('other/not-an-upload.txt', 'ignored');
        $google->put('resumes/1/a.pdf', 'resume');

        $this->artisan('jobauko:move-uploads', ['--to' => 'google', '--dry-run' => true])
            ->expectsOutputToContain('Would copy 2 file(s)')
            ->assertSuccessful();
        $google->assertMissing('applications/2/b.pdf');

        $this->artisan('jobauko:move-uploads', ['--to' => 'google'])
            ->expectsOutputToContain('Copied 2 file(s) from "local" to "google"; 1 already there; 0 failed.')
            ->assertSuccessful();

        $google->assertExists('applications/2/b.pdf');
        $this->assertSame('permit', $google->get('organization-documents/3/c.pdf'));
        $google->assertMissing('other/not-an-upload.txt');
        $local->assertExists('applications/2/b.pdf');
    }

    public function test_moving_needs_a_different_target_disk()
    {
        $this->artisan('jobauko:move-uploads')->assertFailed();
        $this->artisan('jobauko:move-uploads', ['--to' => 'local'])->assertFailed();
    }

    public function test_settings_are_written_into_the_env_file()
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, "APP_NAME=JoBauko\nGOOGLE_DRIVE_FOLDER_ID=old\nMAIL_MAILER=log\n");

        EnvFile::set($path, ['GOOGLE_DRIVE_FOLDER_ID' => 'new-folder', 'GOOGLE_DRIVE_REFRESH_TOKEN' => '1//0g$abc"def']);

        $this->assertSame(
            "APP_NAME=JoBauko\nGOOGLE_DRIVE_FOLDER_ID=\"new-folder\"\nMAIL_MAILER=log\nGOOGLE_DRIVE_REFRESH_TOKEN=\"1//0g\$abc\\\"def\"\n",
            file_get_contents($path),
        );
        unlink($path);
    }
}
