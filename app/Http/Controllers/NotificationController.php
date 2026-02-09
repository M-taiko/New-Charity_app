<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mark a notification as read and redirect to related resource
     */
    public function markAsRead(Notification $notification)
    {
        // Authorization check - ensure user can only mark their own notifications
        if ($notification->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        // Mark as read
        $notification->markAsRead();

        // Redirect to related resource
        $url = $this->getRelatedUrl($notification);

        return redirect($url);
    }

    /**
     * Get the URL for the related resource
     *
     * @param Notification $notification
     * @return string
     */
    private function getRelatedUrl(Notification $notification): string
    {
        // Redirect based on notification's related type
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

        // Default: redirect back to previous page
        return url()->previous();
    }
}
