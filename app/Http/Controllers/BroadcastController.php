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

        try {
            $request->validate([
                'title'         => 'required|string|max:255',
                'message'       => 'required|string|max:2000',
                'level'         => 'required|in:info,warning,danger',
                'expires_at'    => 'nullable|date|after:now',
                'target_type'   => 'required|in:all,user',
                'target_user_id' => 'required_if:target_type,user|nullable|exists:users,id',
            ]);

            // Deactivate previous broadcasts
            Broadcast::where('is_active', true)->update(['is_active' => false]);

            $broadcast = Broadcast::create([
                'created_by'     => auth()->id(),
                'title'          => $request->title,
                'message'        => $request->message,
                'level'          => $request->level,
                'is_active'      => true,
                'expires_at'     => $request->expires_at,
                'target_type'    => $request->target_type,
                'target_user_id' => $request->target_type === 'user' ? $request->target_user_id : null,
            ]);

            $targetLabel = $request->target_type === 'all' ? 'جميع المستخدمين' : 'مستخدم محدد';
            ActivityLogService::log('created', "تم إرسال رسالة عاجلة إلى {$targetLabel}: " . $broadcast->title);

            return back()->with('success', "تم إرسال الرسالة العاجلة إلى {$targetLabel}");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    public function deactivate(Broadcast $broadcast)
    {
        $this->authorize('manage_settings');
        $broadcast->update(['is_active' => false]);
        return back()->with('success', 'تم إيقاف الرسالة');
    }

    public function reactivate(Broadcast $broadcast)
    {
        $this->authorize('manage_settings');

        // Deactivate all previous broadcasts
        Broadcast::where('is_active', true)->update(['is_active' => false]);

        // Create a new broadcast with same content (fresh ID)
        $newBroadcast = Broadcast::create([
            'created_by'     => auth()->id(),
            'title'          => $broadcast->title,
            'message'        => $broadcast->message,
            'level'          => $broadcast->level,
            'is_active'      => true,
            'expires_at'     => now()->addHours(24),
            'target_type'    => $broadcast->target_type,
            'target_user_id' => $broadcast->target_user_id,
        ]);

        $targetLabel = $broadcast->target_type === 'all' ? 'جميع المستخدمين' : 'مستخدم محدد';
        ActivityLogService::log('created', "تم إعادة تفعيل رسالة عاجلة إلى {$targetLabel}: " . $newBroadcast->title);

        return back()->with('success', "تم إعادة تفعيل الرسالة العاجلة إلى {$targetLabel}");
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
