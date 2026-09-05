<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shared in-app notification inbox for every authenticated role. Notifications
 * are per-user rows; the linked content keeps its own scope enforcement.
 */
class NotificationsController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(25);

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'تم تحديد جميع الإشعارات كمقروءة');
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->where('id', $notification)->update(['read_at' => now()]);

        return back();
    }
}
