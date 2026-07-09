<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayoutRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'trading_account_id', 'user_id', 'bank_account_id',
        'requested_amount', 'profit_split_pct', 'trader_amount', 'firm_amount', 'currency',
        'status', 'admin_notes', 'reviewed_by', 'reviewed_at',
        'paystack_transfer_code', 'paystack_reference', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'profit_split_pct' => 'decimal:2',
            'trader_amount' => 'decimal:2',
            'firm_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function tradingAccount()
    {
        return $this->belongsTo(TradingAccount::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(PayoutBankAccount::class, 'bank_account_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
