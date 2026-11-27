<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Support\Api\NotificationLink;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
    public function markAllRead(Request $request)
    {
        // N-06: one instant for the whole batch — markUnread puts back exactly what
        // this call changed (every notification whose read_at is this instant or later)
        $markedAt = now();

        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => $markedAt]);

        // the SPA's /api/v1 call expects a JSON envelope with the message;
        // the Blade web form keeps the redirect + flash
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'تم تعليم جميع الإشعارات كمقروءة',
                'data' => ['marked_at' => $markedAt->toIso8601String()],
            ]);
        }

        return back()->with('success', 'تم تعليم جميع الإشعارات كمقروءة');
    }

    /**
     * Undo of marking notifications read (N-06) — the SPA's undo button.
     * `since`: everything of this user read at that instant or after (what
     * markAllRead returned); `ids`: these few. Only the user's own rows move.
     */
    public function markUnread(Request $request)
    {
        $data = $request->validate([
            'since' => 'nullable|required_without:ids|date',
            'ids' => 'nullable|required_without:since|array|min:1|max:500',
            'ids.*' => 'integer',
        ]);

        $query = Notification::where('user_id', $request->user()->id)->where('is_read', true);
        if (!empty($data['since'])) {
            // a Carbon, so the grammar writes it in the app's timezone — not the raw ISO string
            $query->where('read_at', '>=', Carbon::parse($data['since']));
        } else {
            $query->whereIn('id', $data['ids']);
        }
        $count = $query->update(['is_read' => false, 'read_at' => null]);

        return response()->json([
            'message' => 'تم التراجع: أعيدت الإشعارات إلى غير مقروءة',
            'data' => ['count' => $count],
        ]);
    }

    /**
     * Poll notifications via AJAX
     */
    public function poll()
    {
        $unreadCount = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        $latest = Notification::where('user_id', auth()->id())
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'title', 'message', 'type', 'created_at', 'is_read', 'related_id', 'related_type'])
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'type' => $n->type,
                'is_read' => (bool) $n->is_read,
                'related_id' => $n->related_id,
                'related_type' => $n->related_type,
                'link' => NotificationLink::spa($n->related_type, $n->related_id),
                'created_at' => $n->created_at?->toIso8601ZuluString(),
            ]);

        return response()->json([
            'unread_count' => $unreadCount,
            'latest' => $latest,
        ]);
    }

    /**
     * Get the URL for the related resource
     */
    private function getRelatedUrl(Notification $notification): string
    {
        $type = strtolower($notification->related_type ?? '');

        if ($type === 'social_case' && $notification->related_id) {
            return route('social_cases.show', $notification->related_id);
        }
        if ($type === 'custody' && $notification->related_id) {
            return route('custodies.show', $notification->related_id);
        }
        if ($type === 'expense' && $notification->related_id) {
            return route('expenses.show', $notification->related_id);
        }
        if ($type === 'custody_transfer' && $notification->related_id) {
            return route('custody-transfers.show', $notification->related_id);
        }
        if ($type === 'task' && $notification->related_id) {
            return route('tasks.show', $notification->related_id);
        }
        if ($type === 'purchase_request' && $notification->related_id) {
            return route('purchase-requests.show', $notification->related_id);
        }
        if ($type === 'maintenance_request' && $notification->related_id) {
            return route('maintenance-requests.show', $notification->related_id);
        }

        return route('notifications.index');
    }
}
