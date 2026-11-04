<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Where each upload goes, so the uploads folder (in Google Drive or on disk)
 * is easy to browse: one folder per kind of upload, then one folder per
 * person, application or organization, named with its ID and name.
 *
 *   Resumes/12 - Juan Dela Cruz/Juan Dela Cruz resume (3f9a1c2b).pdf
 *   Application documents/48 - Juan Dela Cruz - Computer Technician/Barangay Clearance (77c0e1d4).pdf
 *   Organization documents/4 - Bauko Farmers Cooperative/business-permit (34e08d59).pdf
 *   Organization logos/4 - Bauko Farmers Cooperative/logo (a1b2c3d4).png
 *
 * The ID keeps folders apart when names repeat; the short code in each file
 * name keeps files apart when the same name is uploaded twice. Paths are
 * stored with each record, so renaming a person or organization later does
 * not move or break earlier files.
 */
final class UploadPaths
{
    public const RESUMES = 'Resumes';

    public const APPLICATIONS = 'Application documents';

    public const ORGANIZATION_DOCUMENTS = 'Organization documents';

    public const ORGANIZATION_LOGOS = 'Organization logos';

    public static function resumes(User $user): string
    {
        return self::RESUMES.'/'.self::folder($user->id, $user->name);
    }

    public static function application(Application $application): string
    {
        $label = ($application->user?->name ?? 'Applicant').' - '.($application->opportunity?->title ?? 'Opportunity');

        return self::APPLICATIONS.'/'.self::folder($application->id, $label);
    }

    public static function organizationDocuments(Organization $organization): string
    {
        return self::ORGANIZATION_DOCUMENTS.'/'.self::folder($organization->id, $organization->name);
    }

    public static function organizationLogos(Organization $organization): string
    {
        return self::ORGANIZATION_LOGOS.'/'.self::folder($organization->id, $organization->name);
    }

    /**
     * A readable, unique file name: the uploaded name (or the given one) plus a short code.
     */
    public static function fileName(UploadedFile|string $nameOrFile, string $extension): string
    {
        $name = $nameOrFile instanceof UploadedFile
            ? pathinfo($nameOrFile->getClientOriginalName(), PATHINFO_FILENAME)
            : $nameOrFile;

        $base = self::clean($name, 80) ?: 'file';

        return $base.' ('.Str::lower(Str::random(8)).').'.Str::lower($extension);
    }

    /**
     * The folder an uploaded file sits in, when it is one of the per-person,
     * per-application or per-organization folders (so it can be removed with the record).
     */
    public static function ownFolder(string $path): ?string
    {
        $folder = dirname($path);

        return substr_count($folder, '/') === 1 ? $folder : null;
    }

    private static function folder(int $id, string $label): string
    {
        return $id.' - '.(self::clean($label, 80) ?: 'Unnamed');
    }

    /**
     * Keeps names safe for Google Drive and Windows/Linux file systems.
     * Apostrophes are dropped: the Drive library cannot find files whose names
     * contain them (Mayor's Permit becomes Mayors Permit; the original name is
     * still kept with the record and used for downloads).
     */
    private static function clean(string $text, int $limit): string
    {
        return (string) Str::of($text)
            ->replaceMatches("/['‘’`]+/u", '')
            ->replaceMatches('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ')
            ->squish()
            ->limit($limit, '')
            ->trim(' .');
    }
}
