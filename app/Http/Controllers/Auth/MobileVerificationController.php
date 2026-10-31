<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Accounts\MobileVerificationService;
use App\Support\MobileNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MobileVerificationController extends Controller
{
    /**
     * Show the mobile verification page.
     */
    public function show(Request $request, MobileVerificationService $verification): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->mobile_number === null) {
            return to_route('profile.edit');
        }

        return Inertia::render('auth/verify-mobile', [
            'mobileNumber' => MobileNumber::mask($user->mobile_number),
            'verified' => $user->hasVerifiedMobile(),
            'codePending' => $verification->hasPendingCode($user),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Send a new verification code.
     */
    public function send(Request $request, MobileVerificationService $verification): RedirectResponse
    {
        $user = $request->user();

        if ($user->mobile_number === null || $user->hasVerifiedMobile()) {
            return to_route('mobile.verification.notice');
        }

        if (! $verification->sendCode($user)) {
            throw ValidationException::withMessages([
                'code' => 'We could not send the code right now. Please try again in a few minutes.',
            ]);
        }

        return back()->with('status', 'verification-code-sent');
    }

    /**
     * Check a submitted code.
     */
    public function verify(Request $request, MobileVerificationService $verification): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (! $verification->verify($request->user(), (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'The code is incorrect or has expired. Request a new code if needed.',
            ]);
        }

        return to_route('dashboard')->with('status', 'mobile-verified');
    }
}
