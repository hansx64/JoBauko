<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\Barangay;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Checks the configuration a live JoBauko server needs. Problems are
 * failures; things that work but deserve attention are warnings.
 */
class CheckProductionReadiness extends Command
{
    protected $signature = 'jobauko:check';

    protected $description = 'Check that this installation is configured safely for production';

    /** @var list<array{string, string, string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->check(app()->isProduction(), 'APP_ENV is production', 'Set APP_ENV=production.');
        $this->check(! config('app.debug'), 'Debug mode is off', 'Set APP_DEBUG=false. Debug pages expose code and data.');
        $this->check(filled(config('app.key')), 'Application key is set', 'Run php artisan key:generate.');
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses HTTPS', 'Serve the site over HTTPS and set APP_URL to the https:// address.');
        $this->check(config('session.secure') === true, 'Session cookies are HTTPS-only', 'Set SESSION_SECURE_COOKIE=true.');
        $this->check((bool) config('session.encrypt'), 'Sessions are encrypted', 'Set SESSION_ENCRYPT=true.');
        $this->check(config('queue.default') !== 'sync', 'A background queue is configured', 'Set QUEUE_CONNECTION=database and run a queue worker.');
        $this->check(! in_array(config('mail.default'), ['log', 'array'], true), 'Email is actually sent', 'Configure MAIL_MAILER (e.g. smtp) so invitations and notices reach people.');
        $this->check(! str_ends_with((string) config('mail.from.address'), '.test'), 'Sender address is real', 'Set MAIL_FROM_ADDRESS to the municipality’s address.');
        $this->check(! str_ends_with((string) config('jobauko.privacy_contact_email'), '.test'), 'Privacy contact is real', 'Set PRIVACY_CONTACT_EMAIL to the Data Protection Officer’s address.');
        $this->warnUnless(config('sms.driver') !== 'log', 'SMS is sent through a gateway', 'SMS_DRIVER=log only writes messages to the log. Set SMS_DRIVER=semaphore and SEMAPHORE_API_KEY to send SMS.');
        $this->warnUnless(config('logging.channels.single.level') !== 'debug', 'Logging is not at debug level', 'Set LOG_LEVEL=warning (or error) to keep personal data out of logs.');

        try {
            DB::connection()->getPdo();
            $this->check(true, 'Database is reachable', '');
            $this->check(Barangay::count() === 22, 'All 22 Bauko barangays are loaded', 'Run php artisan db:seed --class=LocationSeeder.');
            $this->check(User::where('account_type', AccountType::Admin)->where('is_active', true)->exists(), 'An administrator account exists', 'Run php artisan db:seed --class=AdminUserSeeder.');
            $this->warnUnless(! User::where('email', 'like', 'demo.%@jobauko.test')->exists(), 'No demo accounts', 'Demo accounts from DemoDataSeeder exist. Remove them before going live.');
        } catch (Throwable $e) {
            $this->check(false, 'Database is reachable', $e->getMessage());
        }

        $this->check(File::exists(public_path('storage')), 'Public storage is linked', 'Run php artisan storage:link so organization logos display.');
        $this->checkUploads();
        $this->check(File::exists(public_path('build/manifest.json')), 'Frontend is built', 'Run npm ci && npm run build.');
        $this->warnUnless(! File::exists(public_path('hot')), 'Vite dev server is not in use', 'Delete public/hot; it makes pages load scripts from a development server.');

        $this->table(['', 'Check', 'What to do'], $this->results);

        $failed = collect($this->results)->where(0, 'FAIL')->count();
        $warned = collect($this->results)->where(0, 'WARN')->count();

        if ($failed > 0) {
            $this->error("{$failed} ".str('check')->plural($failed)." failed, {$warned} ".str('warning')->plural($warned).'.');

            return self::FAILURE;
        }

        $this->info($warned > 0 ? "Ready, with {$warned} ".str('warning')->plural($warned).'.' : 'Ready for production.');

        return self::SUCCESS;
    }

    /**
     * Resumes and documents must be storable wherever UPLOADS_DISK points.
     */
    private function checkUploads(): void
    {
        $label = Uploads::name() === 'google' ? 'Uploads folder in Google Drive is reachable' : 'Private uploads folder is writable';

        try {
            Uploads::disk()->directoryExists('resumes');
            $ok = Uploads::name() !== 'local' || is_writable(storage_path('app/private'));
            $this->check($ok, $label, 'Make storage/app/private writable by the web server.');
        } catch (Throwable $e) {
            $this->check(false, $label, 'Run php artisan jobauko:google-drive to reconnect. ('.$e->getMessage().')');
        }
    }

    private function check(bool $ok, string $label, string $fix): void
    {
        $this->results[] = [$ok ? 'OK' : 'FAIL', $label, $ok ? '' : $fix];
    }

    private function warnUnless(bool $ok, string $label, string $fix): void
    {
        $this->results[] = [$ok ? 'OK' : 'WARN', $label, $ok ? '' : $fix];
    }
}
