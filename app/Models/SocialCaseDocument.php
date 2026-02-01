<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialCaseDocument extends Model
{
    protected $fillable = [
        'social_case_id',
        'name',
        'file_path',
        'file_type',
    ];

    public function socialCase(): BelongsTo
    {
        return $this->belongsTo(SocialCase::class);
    }
}
