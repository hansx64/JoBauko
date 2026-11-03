<?php

namespace App\Services\Storage;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

/**
 * Builds the "google" storage disk: a folder in a Google Drive, reached with
 * a Google account's OAuth refresh token (see `php artisan jobauko:google-drive`).
 * Only the drive.file scope is used, so the app can see the files it created
 * and nothing else in that Drive. Nothing is sent to Google until a file is
 * actually read or written.
 */
class GoogleDriveDisk
{
    public const SCOPE = Drive::DRIVE_FILE;

    /**
     * @param  array<string, mixed>  $config  the disk's entry in config/filesystems.php
     */
    public static function make(array $config): FilesystemAdapter
    {
        foreach (['client_id', 'client_secret', 'refresh_token', 'folder_id'] as $key) {
            if (blank($config[$key] ?? null)) {
                throw new InvalidArgumentException('Google Drive storage is not configured: GOOGLE_DRIVE_'.strtoupper($key).' is missing. Run php artisan jobauko:google-drive.');
            }
        }

        $client = self::client($config['client_id'], $config['client_secret']);

        // Start with an expired access token: the client fetches a fresh one
        // from the refresh token on the first request.
        $client->setAccessToken([
            'access_token' => 'pending',
            'refresh_token' => $config['refresh_token'],
            'created' => 0,
            'expires_in' => 0,
        ]);

        // The folder is addressed by its ID, so the adapter does not look it
        // up by name (which would call Google on every page load).
        $adapter = new GoogleDriveAdapter(new Drive($client), null, ['sharedFolderId' => $config['folder_id']]);

        return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
    }

    public static function client(string $clientId, string $clientSecret): Client
    {
        $client = new Client;
        $client->setApplicationName('JoBauko');
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setScopes([self::SCOPE]);
        $client->setAccessType('offline');

        return $client;
    }
}
