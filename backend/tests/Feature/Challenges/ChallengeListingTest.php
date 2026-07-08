<?php

use App\Models\Challenge;

it('lists only active challenges ordered by sort order', function () {
    Challenge::factory()->create(['name' => 'Inactive Tier', 'is_active' => false, 'sort_order' => 0]);
    Challenge::factory()->create(['name' => 'Second', 'sort_order' => 2]);
    Challenge::factory()->create(['name' => 'First', 'sort_order' => 1]);

    $response = $this->getJson('/api/v1/challenges');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names)->not->toContain('Inactive Tier');
    expect($names->first())->toBe('First');
});

it('returns 404 for an inactive challenge detail page', function () {
    $challenge = Challenge::factory()->inactive()->create();

    $this->getJson("/api/v1/challenges/{$challenge->id}")->assertNotFound();
});

it('returns challenge rules in the response shape the frontend expects', function () {
    $challenge = Challenge::factory()->create();

    $response = $this->getJson("/api/v1/challenges/{$challenge->id}");

    $response->assertOk()->assertJsonStructure([
        'data' => [
            'id', 'name', 'account_size', 'price', 'currency',
            'rules' => [
                'profit_target_phase1_pct', 'max_daily_drawdown_pct',
                'max_total_drawdown_pct', 'min_trading_days', 'profit_split_pct',
            ],
        ],
    ]);
});
