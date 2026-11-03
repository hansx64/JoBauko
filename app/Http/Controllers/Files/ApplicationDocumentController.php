<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationDocumentController extends Controller
{
    /**
     * Stream an application document to the applicant or to personnel of
     * the organization that received the application.
     */
    public function show(Request $request, ApplicationDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document->application);

        return Uploads::disk()->response(
            $document->disk_path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $request->boolean('preview') && $document->isPreviewable() ? 'inline' : 'attachment',
        );
    }
}
