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

        $messages = ChatMessage::with(['user', 'poll.votes', 'poll.creator'])
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
                'poll'    => $m->poll ? $this->formatPoll($m->poll) : null,
            ]);

        return response()->json($messages);
    }

    /**
     * Format poll data for response
     */
    private function formatPoll($poll)
    {
        $voteCounts = $poll->getVoteCounts();
        $totalVotes = count($poll->votes);
        $userVote = $poll->getUserVote(auth()->id());

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'options' => array_map(function ($option, $index) use ($voteCounts, $totalVotes) {
                $count = $voteCounts[$index];
                $percentage = $totalVotes > 0 ? round(($count / $totalVotes) * 100, 1) : 0;
                return [
                    'text' => $option,
                    'votes' => $count,
                    'percentage' => $percentage,
                ];
            }, $poll->options, array_keys($poll->options)),
            'total_votes' => $totalVotes,
            'is_closed' => $poll->is_closed,
            'user_vote' => $userVote ? $userVote->option_index : null,
            'created_by' => $poll->creator->name,
        ];
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
