<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view_all_records');

        $query = ActivityLog::with('user')->latest();

        // Filters
        if ($request->filled('user_filter')) {
            $query->whereHas('user', fn($q) => $q->where('name', 'like', '%' . $request->user_filter . '%'));
        }
        if ($request->filled('event_filter')) {
            $query->where('event', $request->event_filter);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $events = ActivityLog::select('event')->distinct()->pluck('event');

        return view('activity-logs.index', compact('logs', 'events'));
    }

    /**
     * Show personal activity log for current user
     */
    public function myActivity(Request $request)
    {
        $query = ActivityLog::with('user')->where('user_id', auth()->id())->latest();

        // Filters
        if ($request->filled('event_filter')) {
            $query->where('event', $request->event_filter);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $events = ActivityLog::where('user_id', auth()->id())->select('event')->distinct()->pluck('event');

        return view('activity-logs.my-activity', compact('logs', 'events'));
    }
}
