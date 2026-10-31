<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Classification;
use App\Enums\NotificationTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterSeekerRequest;
use App\Models\Barangay;
use App\Services\Accounts\SeekerRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
            'classifications' => Classification::options(),
            'classificationTopics' => collect(Classification::cases())->mapWithKeys(fn (Classification $c) => [
                $c->value => array_map(fn (NotificationTopic $t) => $t->label(), $c->defaultNotificationTopics()),
            ]),
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterSeekerRequest $request, SeekerRegistrationService $registration): RedirectResponse
    {
        $user = $registration->register($request->validated(), $request);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
