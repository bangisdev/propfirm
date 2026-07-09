<?php

namespace App\Http\Controllers\Api\V1\Payouts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payouts\RequestPayoutRequest;
use App\Http\Requests\Payouts\StoreBankAccountRequest;
use App\Http\Resources\PayoutBankAccountResource;
use App\Http\Resources\PayoutRequestResource;
use App\Models\PayoutBankAccount;
use App\Models\PayoutRequest;
use App\Models\TradingAccount;
use App\Services\Payments\PaystackService;
use App\Services\Payouts\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutService $payouts,
        private readonly PaystackService $paystack,
    ) {}

    /**
     * GET /api/v1/payout-bank-accounts
     */
    public function bankAccounts(Request $request): JsonResponse
    {
        $accounts = PayoutBankAccount::where('user_id', $request->user()->id)->latest()->get();

        return response()->json(['data' => PayoutBankAccountResource::collection($accounts)]);
    }

    /**
     * POST /api/v1/payout-bank-accounts
     * Resolves the account number against Paystack first, so a typo is caught
     * before it's saved (and long before we'd ever try to pay it).
     */
    public function storeBankAccount(StoreBankAccountRequest $request): JsonResponse
    {
        $resolved = $this->paystack->resolveAccountNumber(
            $request->input('account_number'),
            $request->input('bank_code'),
        );

        $bankAccount = DB::transaction(function () use ($request, $resolved) {
            // Only one default at a time — newly added accounts become the default.
            PayoutBankAccount::where('user_id', $request->user()->id)->update(['is_default' => false]);

            return PayoutBankAccount::create([
                'user_id' => $request->user()->id,
                'bank_name' => $request->input('bank_name'),
                'bank_code' => $request->input('bank_code'),
                'account_number' => $request->input('account_number'),
                'account_name' => $resolved['account_name'],
                'currency' => $request->input('currency', 'NGN'),
                'is_default' => true,
            ]);
        });

        return response()->json(['data' => new PayoutBankAccountResource($bankAccount)], 201);
    }

    /**
     * GET /api/v1/payout-requests
     */
    public function index(Request $request): JsonResponse
    {
        $payouts = PayoutRequest::where('user_id', $request->user()->id)
            ->with('bankAccount')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => PayoutRequestResource::collection($payouts),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/payout-requests
     */
    public function store(RequestPayoutRequest $request): JsonResponse
    {
        $account = TradingAccount::where('id', $request->input('trading_account_id'))
            ->where('user_id', $request->user()->id)
            ->with('challenge')
            ->firstOrFail();

        $bankAccount = PayoutBankAccount::where('id', $request->input('bank_account_id'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $payout = $this->payouts->request($account, $bankAccount, (float) $request->input('amount'));

        return response()->json(['data' => new PayoutRequestResource($payout->load('bankAccount'))], 201);
    }
}
