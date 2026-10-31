<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seeker\AddApplicationDocumentRequest;
use App\Models\Application;
use App\Services\Applications\ApplicationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ApplicationDocumentController extends Controller
{
    /**
     * Add a document the organization asked for.
     */
    public function store(AddApplicationDocumentRequest $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('addDocument', $application);

        $workflow->addDocument($application, $request->user(), $request->validated('label'), $request->file('file'));

        return back()->with('success', 'Document added. The organization has been notified.');
    }
}
