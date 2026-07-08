<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'slug', 'phase_count', 'account_size', 'currency', 'price',
        'profit_target_phase1_pct', 'profit_target_phase2_pct',
        'max_daily_drawdown_pct', 'max_total_drawdown_pct',
        'min_trading_days', 'profit_split_pct',
        'news_trading_restricted', 'weekend_holding_allowed',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'account_size' => 'decimal:2',
            'price' => 'decimal:2',
            'profit_target_phase1_pct' => 'decimal:2',
            'profit_target_phase2_pct' => 'decimal:2',
            'max_daily_drawdown_pct' => 'decimal:2',
            'max_total_drawdown_pct' => 'decimal:2',
            'profit_split_pct' => 'decimal:2',
            'news_trading_restricted' => 'boolean',
            'weekend_holding_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
