<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradingAccount extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'order_id', 'challenge_id',
        'mt5_login', 'mt5_password_encrypted', 'mt5_server',
        'phase', 'status',
        'starting_balance', 'current_balance', 'current_equity', 'highest_balance',
        'first_trade_date', 'trading_days_count',
        'provisioned_at', 'breached_at', 'breach_reason', 'last_synced_at',
    ];

    protected $hidden = ['mt5_password_encrypted'];

    protected function casts(): array
    {
        return [
            'starting_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'current_equity' => 'decimal:2',
            'highest_balance' => 'decimal:2',
            'first_trade_date' => 'date',
            'provisioned_at' => 'datetime',
            'breached_at' => 'datetime',
            'last_synced_at' => 'datetime',
            // Encrypted cast: transparently encrypts on write, decrypts on read,
            // using APP_KEY — MT5 investor passwords must never sit in plaintext.
            'mt5_password_encrypted' => 'encrypted',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function isProvisioned(): bool
    {
        return ! is_null($this->mt5_login);
    }
}
