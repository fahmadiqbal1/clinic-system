<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Show paginated notification history for the current user.
     */
    public function index(): View
    {
        /** @var User $user */
        $user          = Auth::user();
        $notifications = $user->notifications()->latest()->paginate(25);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Return unread notifications as JSON (for AJAX polling).
     */
    public function unread(): JsonResponse
    {
        /** @var User $user */
        $user          = Auth::user();
        $notifications = $user->unreadNotifications()->limit(10)->get();
        $count         = $user->unreadNotifications()->count();

        return response()->json([
            'count' => $count,
            'notifications' => $notifications->map(function ($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? 'Notification',
                    'message' => $n->data['message'] ?? '',
                    'icon' => $n->data['icon'] ?? 'bi-bell',
                    'url' => $n->data['url'] ?? '#',
                    'color' => $n->data['color'] ?? 'primary',
                    'assigned_at' => $n->data['assigned_at'] ?? $n->created_at->toIso8601String(),
                    'created_at' => $n->created_at->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(string $id): JsonResponse
    {
        /** @var User $user */
        $user         = Auth::user();
        $notification = $user->notifications->find($id);

        if (!$notification) {
            return response()->json(['ok' => false], 404);
        }

        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}
