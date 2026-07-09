<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AffiliateCommission extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_user_id', 'referred_user_id', 'order_id',
        'order_amount', 'commission_pct', 'commission_amount', 'currency',
        'status', 'paystack_transfer_code', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'order_amount' => 'decimal:2',
            'commission_pct' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
