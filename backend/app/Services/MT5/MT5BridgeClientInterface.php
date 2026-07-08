<?php

namespace App\Services\MT5;

interface MT5BridgeClientInterface
{
    /**
     * Provisions a new MT5 account for a challenge.
     *
     * @param array{group: string, balance: float, leverage: int, name: string, email: string} $params
     * @return array{login: int, password: string, server: string}
     */
    public function provisionAccount(array $params): array;

    /**
     * Fetches current account state from MT5 for rule-engine evaluation (Phase 3).
     *
     * @return array{balance: float, equity: float, open_positions: int, last_trade_at: ?string}
     */
    public function fetchAccountState(int $login): array;

    /**
     * Disables trading on an account (e.g. after a breach or evaluation failure).
     */
    public function disableAccount(int $login): void;
}
