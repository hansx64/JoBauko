<?php

namespace App\Http\Controllers\Organization;

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organization\Concerns\ResolvesOrganization;
use App\Http\Requests\Organization\InvitePersonnelRequest;
use App\Models\OrganizationMember;
use App\Services\Organizations\PersonnelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersonnelController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): Response
    {
        $organization = $this->organization($request);

        $members = $organization->members()
            ->with('user')
            ->get()
            ->sortBy(fn (OrganizationMember $m) => [! $m->is_active, $m->role !== OrganizationRole::Admin, $m->user->name])
            ->values();

        return Inertia::render('organization/personnel', [
            'organization' => $this->organizationSummary($organization),
            'members' => $members->map(fn (OrganizationMember $member) => [
                'id' => $member->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
                'is_active' => $member->is_active,
                'is_you' => (int) $member->user_id === (int) $request->user()->id,
                'invitation_pending' => PersonnelService::invitationPending($member),
                'last_login_at' => $member->user->last_login_at?->toIso8601String(),
                'joined_at' => $member->created_at?->toIso8601String(),
            ]),
            'roles' => array_map(fn (OrganizationRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'permissions' => array_map(fn (OrganizationPermission $p) => $p->label(), $role->permissions()),
            ], OrganizationRole::cases()),
        ]);
    }

    public function store(InvitePersonnelRequest $request, PersonnelService $personnel): RedirectResponse
    {
        $member = $personnel->invite(
            $this->organization($request),
            $request->user(),
            $request->validated('name'),
            $request->validated('email'),
            OrganizationRole::from($request->validated('role')),
        );

        return back()->with('success', "Invitation sent to {$member->user->email}.");
    }

    public function update(Request $request, OrganizationMember $member, PersonnelService $personnel): RedirectResponse
    {
        $this->ensureOwnMember($request, $member);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(OrganizationRole::class)],
            'is_active' => ['required', 'boolean'],
        ]);

        $personnel->update($member, $request->user(), OrganizationRole::from($validated['role']), (bool) $validated['is_active']);

        return back()->with('success', "{$member->user->name}'s access was updated.");
    }

    public function resend(Request $request, OrganizationMember $member, PersonnelService $personnel): RedirectResponse
    {
        $this->ensureOwnMember($request, $member);

        $personnel->resendInvitation($member);

        return back()->with('success', "A new invitation was sent to {$member->user->email}.");
    }

    private function ensureOwnMember(Request $request, OrganizationMember $member): void
    {
        abort_unless((int) $member->organization_id === (int) $this->organization($request)->id, 404);
    }
}
