<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyMember extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'social_case_id',
        'name',
        'relationship',
        'gender',
        'phone',
    ];

    protected $casts = [
        'social_case_id' => 'integer',
    ];

    public function socialCase()
    {
        return $this->belongsTo(SocialCase::class);
    }
}
