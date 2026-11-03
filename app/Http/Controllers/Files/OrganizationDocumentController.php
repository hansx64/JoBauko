<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\OrganizationDocument;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationDocumentController extends Controller
{
    /**
     * Stream a verification document to administrators or the organization's
     * own personnel. PDFs and images can be previewed inline.
     */
    public function show(Request $request, OrganizationDocument $document): StreamedResponse
    {
        Gate::authorize('viewDocuments', $document->organization);

        return Uploads::disk()->response(
            $document->disk_path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $request->boolean('preview') ? 'inline' : 'attachment',
        );
    }
}
