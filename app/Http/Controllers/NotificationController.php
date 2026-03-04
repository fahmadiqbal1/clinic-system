<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Return unread notifications as JSON (for AJAX polling).
     */
    public function unread(): JsonResponse
    {
        $notifications = Auth::user()->unreadNotifications->take(10);

        return response()->json([
            'count' => Auth::user()->unreadNotifications->count(),
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
    public function markRead(string $id)
    {
        $notification = Auth::user()->notifications->find($id);
        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): RedirectResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}
