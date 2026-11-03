<?php

namespace App\Console\Commands;

use App\Services\Mail\GmailTransport;
use App\Services\Storage\GoogleDriveDisk;
use App\Support\EnvFile;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-time setup for storing uploads in Google Drive and sending email with
 * Gmail: signs in to the Google account in the browser, then saves the
 * refresh token and the uploads folder in .env. Secrets are written to .env,
 * never printed.
 */
class ConnectGoogleDrive extends Command
{
    protected $signature = 'jobauko:google-drive
        {--folder=JoBauko uploads : Name of the Drive folder to create for uploads}
        {--timeout=300 : Seconds to wait for the browser sign-in}';

    protected $description = 'Connect a Google account to store uploads in Google Drive and send email with Gmail';

    public function handle(): int
    {
        $config = config('filesystems.disks.google');

        if (blank($config['client_id'] ?? null) || blank($config['client_secret'] ?? null)) {
            $this->error('Set GOOGLE_DRIVE_CLIENT_ID and GOOGLE_DRIVE_CLIENT_SECRET in .env first (an OAuth client of type "Desktop app"). See docs/DEPLOYMENT.md.');

            return self::FAILURE;
        }

        // Google sends the browser back to this computer once the account owner agrees.
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($server === false) {
            $this->error("Could not listen for Google's response: {$errorMessage}");

            return self::FAILURE;
        }
        $port = (int) Str::afterLast((string) stream_socket_get_name($server, false), ':');

        $state = Str::random(40);
        $client = GoogleDriveDisk::client($config['client_id'], $config['client_secret']);
        $client->setRedirectUri("http://127.0.0.1:{$port}");
        $client->setPrompt('consent');
        $client->setState($state);

        $this->info('Open this link, sign in with the Google account that will hold the uploads, and allow access:');
        $this->newLine();
        $this->line($client->createAuthUrl());
        $this->newLine();
        $this->line('Waiting for the sign-in to finish...');

        $query = $this->waitForRedirect($server, (int) $this->option('timeout'));
        fclose($server);

        if ($query === null) {
            $this->error('No response from the browser. Run the command again.');

            return self::FAILURE;
        }
        if (($query['state'] ?? null) !== $state) {
            $this->error('The response did not match this sign-in. Run the command again.');

            return self::FAILURE;
        }
        if (isset($query['error']) || blank($query['code'] ?? null)) {
            $this->error('Google did not grant access: '.($query['error'] ?? 'no code returned').'.');

            return self::FAILURE;
        }

        $token = $client->fetchAccessTokenWithAuthCode($query['code']);
        if (isset($token['error']) || blank($token['refresh_token'] ?? null)) {
            $this->error('Google did not return a refresh token: '.($token['error_description'] ?? $token['error'] ?? 'unknown error').'.');

            return self::FAILURE;
        }

        try {
            $folderId = $this->uploadsFolder($client, (string) ($config['folder_id'] ?? ''), (string) $this->option('folder'));
        } catch (Throwable $e) {
            $this->error('Signed in, but the uploads folder could not be created: '.$e->getMessage());

            return self::FAILURE;
        }

        EnvFile::set(base_path('.env'), [
            'GOOGLE_DRIVE_REFRESH_TOKEN' => $token['refresh_token'],
            'GOOGLE_DRIVE_FOLDER_ID' => $folderId,
        ]);

        $this->info('Connected. GOOGLE_DRIVE_REFRESH_TOKEN and GOOGLE_DRIVE_FOLDER_ID were saved in .env.');

        // Google lets the account owner untick individual permissions.
        $granted = explode(' ', (string) ($token['scope'] ?? ''));
        if (! in_array(GmailTransport::SCOPE, $granted, true)) {
            $this->warn('Sending email was not allowed, so MAIL_MAILER=gmail will not work. Run this command again and tick every box.');
        }
        $this->newLine();
        $this->line('Next:');
        $this->line('  1. php artisan config:clear');
        $this->line('  2. php artisan jobauko:move-uploads --to=google   (copies existing files to Drive)');
        $this->line('  3. Set UPLOADS_DISK=google in .env, then php artisan config:clear again');
        $this->line('  4. To send email from this Google account: set MAIL_MAILER=gmail and MAIL_FROM_ADDRESS to its address');

        return self::SUCCESS;
    }

    /**
     * Accepts one browser request on the local listener and returns its query string values.
     *
     * @param  resource  $server
     * @return array<string, string>|null
     */
    private function waitForRedirect($server, int $timeout): ?array
    {
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            $connection = @stream_socket_accept($server, max(1, $deadline - time()));
            if ($connection === false) {
                continue;
            }

            $requestLine = (string) fgets($connection);
            $target = explode(' ', $requestLine)[1] ?? '/';
            parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

            // Browsers also ask for /favicon.ico; only the redirect carries a state.
            if (! isset($query['state'])) {
                fwrite($connection, "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
                fclose($connection);

                continue;
            }

            $body = '<!doctype html><meta charset="utf-8"><title>JoBauko</title><p style="font-family:sans-serif">Google Drive is connected to JoBauko. You can close this tab and return to the terminal.</p>';
            fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body);
            fclose($connection);

            /** @var array<string, string> $query */
            return $query;
        }

        return null;
    }

    /**
     * Reuses the configured folder when this account can still reach it, so
     * reconnecting does not leave earlier uploads behind; otherwise creates one.
     */
    private function uploadsFolder(Client $client, string $existingId, string $name): string
    {
        $drive = new Drive($client);

        if ($existingId !== '') {
            try {
                $folder = $drive->files->get($existingId, ['fields' => 'id, trashed']);
                if (! $folder->getTrashed()) {
                    $this->line("Using the existing uploads folder ({$existingId}).");

                    return $existingId;
                }
            } catch (Throwable) {
                // Not reachable with this account; make a new folder.
            }
        }

        $folder = $drive->files->create(new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]), ['fields' => 'id']);

        $this->line("Created the Drive folder \"{$name}\".");

        return $folder->getId();
    }
}
