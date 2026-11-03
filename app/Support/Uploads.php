<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Where uploads live: resumes, application documents, organization
 * verification documents and organization logos. All are served through
 * controllers; only logos are open to everyone. The disk is chosen with
 * UPLOADS_DISK: "local" (storage/app/private) or "google" (Google Drive).
 */
class Uploads
{
    /** Top-level folders holding uploads, used when moving them between disks. */
    public const FOLDERS = ['resumes', 'applications', 'organization-documents', 'organization-logos'];

    public static function name(): string
    {
        return (string) config('jobauko.uploads_disk', 'local');
    }

    public static function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::name());

        return $disk;
    }
}
