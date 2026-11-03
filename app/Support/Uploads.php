<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Where private uploads live: resumes, application documents and
 * organization verification documents. They are never public and are only
 * served through controllers that check who is asking. The disk is chosen
 * with UPLOADS_DISK: "local" (storage/app/private) or "google" (Google Drive).
 */
class Uploads
{
    /** Top-level folders holding private uploads, used when moving them between disks. */
    public const FOLDERS = ['resumes', 'applications', 'organization-documents'];

    public static function name(): string
    {
        return (string) config('jobauko.uploads_disk', 'local');
    }

    public static function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk(self::name());
    }
}
