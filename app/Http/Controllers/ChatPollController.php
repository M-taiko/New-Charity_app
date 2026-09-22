<?php

namespace App\Http\Controllers;

use App\Models\ChatPoll;
use App\Models\ChatPollVote;
use Illuminate\Http\Request;

class ChatPollController extends Controller
{
    /**
     * Store a new poll
     */
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:200',
            'chat_message_id' => 'nullable|exists:chat_messages,id',
        ]);

        $poll = ChatPoll::create([
            'question' => $request->question,
            'options' => $request->options,
            'created_by' => auth()->id(),
            'chat_message_id' => $request->chat_message_id,
        ]);

        return response()->json($this->formatPoll($poll), 201);
    }

    /**
     * Record a vote for a poll
     */
    public function vote(ChatPoll $poll, Request $request)
    {
        $request->validate([
            'option_index' => 'required|integer|min:0',
        ]);

        // Validate option index is within range
        if ($request->option_index >= count($poll->options)) {
            return response()->json(['error' => 'Invalid option index'], 422);
        }

        // Check if poll is closed
        if ($poll->is_closed) {
            return response()->json(['error' => 'Poll is closed'], 422);
        }

        // Delete existing vote if any
        ChatPollVote::where('poll_id', $poll->id)
            ->where('user_id', auth()->id())
            ->delete();

        // Create new vote
        ChatPollVote::create([
            'poll_id' => $poll->id,
            'user_id' => auth()->id(),
            'option_index' => $request->option_index,
        ]);

        return response()->json($this->formatPoll($poll));
    }

    /**
     * Close a poll
     */
    public function close(ChatPoll $poll)
    {
        if ($poll->created_by !== auth()->id() && !auth()->user()->hasRole('مدير')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $poll->update(['is_closed' => true]);

        return response()->json($this->formatPoll($poll));
    }

    /**
     * Get poll details with results
     */
    public function show(ChatPoll $poll)
    {
        return response()->json($this->formatPoll($poll));
    }

    /**
     * Format poll with vote counts and user's vote
     */
    private function formatPoll(ChatPoll $poll)
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
            'created_at' => $poll->created_at,
        ];
    }
}
