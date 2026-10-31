<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\RegisterOrganizationRequest;
use App\Models\Barangay;
use App\Services\Organizations\OrganizationRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/register-organization', [
            'organizationTypes' => OrganizationType::options(),
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(RegisterOrganizationRequest $request, OrganizationRegistrationService $registration): RedirectResponse
    {
        $user = $registration->register(
            $request->safe()->except(['logo', 'documents']),
            $request->file('logo'),
            $request->file('documents', []),
            $request,
        );

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('org.dashboard');
    }
}
