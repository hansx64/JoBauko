<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\NotificationController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => $request->user()?->only([
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'mobile_number',
                    'mobile_verified_at',
                    'barangay_id',
                    'account_type',
                    'created_at',
                    'updated_at',
                ]),
                'organization' => fn () => $this->organizationAccess($request),
            ],
            'notifications' => fn () => $this->notificationSummary($request),
        ]);
    }

    /**
     * Unread count and the latest few, for the bell in the header.
     *
     * @return array{unread: int, recent: list<array<string, mixed>>}|null
     */
    private function notificationSummary(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'unread' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->latest()->limit(5)->get()
                ->map(fn (DatabaseNotification $n) => NotificationController::present($n))
                ->all(),
        ];
    }

    /**
     * The signed-in member's role and permissions, so navigation only shows
     * what the role can use. Routes and policies still enforce them.
     *
     * @return array{name: string, role: string, role_label: string, permissions: list<string>}|null
     */
    private function organizationAccess(Request $request): ?array
    {
        $membership = $request->user()?->organizationMembership;

        if ($membership === null || ! $membership->is_active) {
            return null;
        }

        return [
            'name' => $membership->organization->name,
            'role' => $membership->role->value,
            'role_label' => $membership->role->label(),
            'permissions' => array_map(fn (OrganizationPermission $p) => $p->value, $membership->role->permissions()),
        ];
    }
}
