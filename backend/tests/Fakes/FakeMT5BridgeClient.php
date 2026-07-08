<?php

namespace Tests\Fakes;

use App\Services\MT5\MT5BridgeClientInterface;

class FakeMT5BridgeClient implements MT5BridgeClientInterface
{
    public static bool $shouldFail = false;
    public static array $provisionedCalls = [];

    public function provisionAccount(array $params): array
    {
        self::$provisionedCalls[] = $params;

        if (self::$shouldFail) {
            throw new \RuntimeException('Simulated MT5 bridge outage.');
        }

        return [
            'login' => random_int(1000000, 9999999),
            'password' => 'Inv3st0r-'.bin2hex(random_bytes(4)),
            'server' => 'PropFirm-Demo',
        ];
    }

    public function fetchAccountState(int $login): array
    {
        return [
            'balance' => 10000.0,
            'equity' => 10000.0,
            'open_positions' => 0,
            'last_trade_at' => null,
        ];
    }

    public function disableAccount(int $login): void
    {
        //
    }

    public static function reset(): void
    {
        self::$shouldFail = false;
        self::$provisionedCalls = [];
    }
}
