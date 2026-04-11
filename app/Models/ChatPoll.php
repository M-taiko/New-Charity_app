<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatPoll extends Model
{
    protected $fillable = [
        'chat_message_id',
        'question',
        'options',
        'created_by',
        'is_closed',
    ];

    protected $casts = [
        'options' => 'array',
        'is_closed' => 'boolean',
    ];

    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ChatPollVote::class, 'poll_id');
    }

    /**
     * Get vote counts for each option
     */
    public function getVoteCounts()
    {
        $counts = array_fill(0, count($this->options), 0);
        $votes = $this->votes()->get();

        foreach ($votes as $vote) {
            $counts[$vote->option_index]++;
        }

        return $counts;
    }

    /**
     * Get user's vote for this poll
     */
    public function getUserVote($userId)
    {
        return $this->votes()->where('user_id', $userId)->first();
    }
}
