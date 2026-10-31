<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Organizations\PersonnelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invited organization personnel set their password from the signed link
 * in their invitation email. The link proves the email address, so the
 * account is verified at the same time.
 */
class InvitationController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $member = $this->pendingMembership($user);

        return Inertia::render('auth/accept-invitation', [
            'name' => $user->name,
            'email' => $user->email,
            'organization' => $member->organization->name,
            'role' => $member->role->label(),
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $this->pendingMembership($user);

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->password = $request->string('password')->toString();
        $user->last_login_at = now();
        $user->save();
        $user->markEmailAsVerified();

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('org.dashboard')->with('success', 'Welcome to JoBauko! Your account is ready.');
    }

    private function pendingMembership(User $user): OrganizationMember
    {
        $member = $user->organizationMembership;

        abort_unless($member !== null && $member->is_active && PersonnelService::invitationPending($member), 410, 'This invitation has already been used or is no longer valid.');

        return $member;
    }
}
