<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Affiliate\AffiliatePayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliatePayoutController extends Controller
{
    public function __construct(private readonly AffiliatePayoutService $payouts) {}

    /**
     * POST /api/v1/admin/affiliates/{user}/payout
     */
    public function payout(Request $request, User $user): JsonResponse
    {
        $this->authorize('withdrawals.approve');

        $result = $this->payouts->payoutPending($user);

        return response()->json(['data' => $result]);
    }
}
