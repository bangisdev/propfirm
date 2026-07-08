<?php

namespace App\Http\Controllers\Api\V1\TradingAccounts;

use App\Http\Controllers\Controller;
use App\Http\Resources\TradingAccountResource;
use App\Models\TradingAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradingAccountController extends Controller
{
    /**
     * GET /api/v1/trading-accounts
     */
    public function index(Request $request): JsonResponse
    {
        $accounts = TradingAccount::with('challenge')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['data' => TradingAccountResource::collection($accounts)]);
    }

    /**
     * GET /api/v1/trading-accounts/{tradingAccount}/credentials
     * Separate, explicit endpoint for revealing the MT5 password — kept out of the
     * regular index/show payload so credentials aren't logged/cached incidentally
     * alongside routine dashboard polling.
     */
    public function credentials(TradingAccount $tradingAccount, Request $request): JsonResponse
    {
        abort_if($tradingAccount->user_id !== $request->user()->id, 403);
        abort_if(! $tradingAccount->isProvisioned(), 409, 'Account is still being provisioned.');

        return response()->json([
            'data' => [
                'mt5_login' => $tradingAccount->mt5_login,
                'mt5_password' => $tradingAccount->mt5_password_encrypted, // decrypted transparently by the cast
                'mt5_server' => $tradingAccount->mt5_server,
            ],
        ]);
    }
}
