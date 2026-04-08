<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    public function index()
    {
        $this->authorize('manage_settings');
        $broadcasts = Broadcast::with('creator')->latest()->paginate(20);
        return view('broadcasts.index', compact('broadcasts'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage_settings');

        $request->validate([
            'title'      => 'required|string|max:255',
            'message'    => 'required|string|max:2000',
            'level'      => 'required|in:info,warning,danger',
            'expires_at' => 'nullable|date|after:now',
        ]);

        // Deactivate previous broadcasts
        Broadcast::where('is_active', true)->update(['is_active' => false]);

        $broadcast = Broadcast::create([
            'created_by' => auth()->id(),
            'title'      => $request->title,
            'message'    => $request->message,
            'level'      => $request->level,
            'is_active'  => true,
            'expires_at' => $request->expires_at,
        ]);

        ActivityLogService::log('created', 'تم إرسال رسالة عاجلة: ' . $broadcast->title);

        return back()->with('success', 'تم إرسال الرسالة العاجلة لجميع المستخدمين');
    }

    public function deactivate(Broadcast $broadcast)
    {
        $this->authorize('manage_settings');
        $broadcast->update(['is_active' => false]);
        return back()->with('success', 'تم إيقاف الرسالة');
    }

    public function dismiss(Request $request)
    {
        // Store dismissed broadcast IDs in session
        $broadcast = Broadcast::activeNow();
        if ($broadcast) {
            $dismissed = session('dismissed_broadcasts', []);
            $dismissed[] = $broadcast->id;
            session(['dismissed_broadcasts' => $dismissed]);
        }
        return response()->json(['ok' => true]);
    }
}
