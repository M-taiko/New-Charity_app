<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        $messages = ChatMessage::with('user')->latest()->limit(100)->get()->reverse()->values();
        return view('chat.index', compact('messages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $msg = ChatMessage::create([
            'user_id' => auth()->id(),
            'body'    => $request->body,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'id' => $msg->id]);
        }

        return back();
    }

    /**
     * API: return messages newer than given ID (for polling)
     */
    public function poll(Request $request)
    {
        $since = (int) $request->get('since', 0);

        $messages = ChatMessage::with('user')
            ->where('id', '>', $since)
            ->oldest()
            ->limit(50)
            ->get()
            ->map(fn($m) => [
                'id'      => $m->id,
                'user_id' => $m->user_id,
                'name'    => $m->user->name,
                'body'    => $m->body,
                'time'    => $m->created_at->diffForHumans(),
                'is_me'   => $m->user_id === auth()->id(),
            ]);

        return response()->json($messages);
    }

    public function destroy(ChatMessage $chatMessage)
    {
        // Only author or manager can delete
        $user = auth()->user();
        if ($chatMessage->user_id !== $user->id && !$user->hasRole('مدير')) {
            abort(403);
        }
        $chatMessage->delete();
        return response()->json(['ok' => true]);
    }
}
