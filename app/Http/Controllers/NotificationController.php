<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Show all notifications for the current user
     */
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        $unreadCount = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Mark a notification as read and redirect to related resource
     */
    public function markAsRead(Notification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $notification->markAsRead();

        $url = $this->getRelatedUrl($notification);

        return redirect($url);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead()
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'تم تعليم جميع الإشعارات كمقروءة');
    }

    /**
     * Get the URL for the related resource
     */
    private function getRelatedUrl(Notification $notification): string
    {
        if ($notification->related_type === 'social_case' && $notification->related_id) {
            return route('social_cases.show', $notification->related_id);
        }
        if ($notification->related_type === 'custody' && $notification->related_id) {
            return route('custodies.show', $notification->related_id);
        }
        if ($notification->related_type === 'expense' && $notification->related_id) {
            return route('expenses.show', $notification->related_id);
        }
        if ($notification->related_type === 'custody_transfer' && $notification->related_id) {
            return route('custody-transfers.show', $notification->related_id);
        }
        if ($notification->related_type === 'task' && $notification->related_id) {
            return route('tasks.show', $notification->related_id);
        }
        if ($notification->related_type === 'purchase_request' && $notification->related_id) {
            return route('purchase-requests.show', $notification->related_id);
        }
        if ($notification->related_type === 'maintenance_request' && $notification->related_id) {
            return route('maintenance-requests.show', $notification->related_id);
        }

        return route('notifications.index');
    }
}
