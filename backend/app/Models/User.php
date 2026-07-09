<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasUuids, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $guard_name = 'api';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'country_code',
        'timezone',
        'referral_code',
        'referred_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'is_suspended' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // --- JWTSubject ---

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Custom claims embedded in the access token so the frontend/gateway
     * can make coarse-grained decisions without an extra DB round trip.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->getRoleNames()->first(),
            'kyc_status' => $this->kyc_status,
        ];
    }

    // --- Relationships ---

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function affiliateCommissionsEarned()
    {
        return $this->hasMany(AffiliateCommission::class, 'affiliate_user_id');
    }

    public function kycSubmissions()
    {
        return $this->hasMany(KycSubmission::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // --- Activity log config ---

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'kyc_status', 'is_suspended'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
