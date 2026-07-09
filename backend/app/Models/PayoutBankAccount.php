<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayoutBankAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'bank_name', 'bank_code', 'account_number', 'account_name',
        'currency', 'paystack_recipient_code', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class, 'bank_account_id');
    }

    /**
     * Masks the account number for display — only the last 4 digits are shown,
     * consistent with how card/account numbers are normally surfaced in a UI.
     */
    public function getMaskedAccountNumberAttribute(): string
    {
        $number = $this->account_number;

        return str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4);
    }
}
