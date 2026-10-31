<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's in-app notifications. A notification is looked up
 * through the user's own relation, so others' notifications are not found.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $unreadOnly = $request->query('filter') === 'unread';

        return Inertia::render('notifications/index', [
            'notifications' => ($unreadOnly ? $request->user()->unreadNotifications() : $request->user()->notifications())
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (DatabaseNotification $n) => self::present($n)),
            'filter' => $unreadOnly ? 'unread' : 'all',
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark as read and go to what the notification is about.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Only follow links within the portal.
        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : to_route('notifications.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'kind' => class_basename($notification->type),
            'title' => $data['title'] ?? null,
            'message' => (string) ($data['message'] ?? ''),
            'body' => $data['body'] ?? null,
            'has_link' => isset($data['url']) && is_string($data['url']),
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
