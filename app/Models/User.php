<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'profile_picture',
        'is_active',
        'is_hidden',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_hidden' => 'boolean',
        ];
    }

    public function custodiesAsAgent()
    {
        return $this->hasMany(Custody::class, 'agent_id');
    }

    public function custodiesAsAccountant()
    {
        return $this->hasMany(Custody::class, 'accountant_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function socialCases()
    {
        return $this->hasMany(SocialCase::class, 'researcher_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function treasuryTransactions()
    {
        return $this->hasMany(TreasuryTransaction::class);
    }

    public function transfersSent()
    {
        return $this->hasMany(CustodyTransfer::class, 'from_agent_id');
    }

    public function transfersReceived()
    {
        return $this->hasMany(CustodyTransfer::class, 'to_agent_id');
    }
}
