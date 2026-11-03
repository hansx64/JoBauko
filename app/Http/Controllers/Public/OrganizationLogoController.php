<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Uploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logos are public, but they live with the other uploads (local disk or
 * Google Drive) so they survive hosts whose own disk is wiped on restart.
 */
class OrganizationLogoController extends Controller
{
    public function __invoke(Organization $organization): StreamedResponse
    {
        abort_unless($organization->logo_path && Uploads::disk()->exists($organization->logo_path), 404);

        // The URL carries a version derived from the path, so a new logo gets a new URL.
        return Uploads::disk()->response($organization->logo_path, null, [
            'Cache-Control' => 'public, max-age=604800, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
