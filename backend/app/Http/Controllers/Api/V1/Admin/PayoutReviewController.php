<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectPayoutRequest;
use App\Http\Requests\Admin\ReviewPayoutRequest;
use App\Http\Resources\PayoutRequestResource;
use App\Models\PayoutRequest;
use App\Services\Payouts\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutReviewController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    /**
     * GET /api/v1/admin/payout-requests?status=pending
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('withdrawals.approve');

        $query = PayoutRequest::with(['bankAccount', 'user', 'tradingAccount.challenge'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payouts = $query->paginate(20);

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
     * POST /api/v1/admin/payout-requests/{payoutRequest}/approve
     */
    public function approve(ReviewPayoutRequest $request, PayoutRequest $payoutRequest): JsonResponse
    {
        $payout = $this->payouts->approve($payoutRequest, $request->user(), $request->input('notes'));

        return response()->json(['data' => new PayoutRequestResource($payout->load('bankAccount'))]);
    }

    /**
     * POST /api/v1/admin/payout-requests/{payoutRequest}/reject
     */
    public function reject(RejectPayoutRequest $request, PayoutRequest $payoutRequest): JsonResponse
    {
        $payout = $this->payouts->reject($payoutRequest, $request->user(), $request->input('reason'));

        return response()->json(['data' => new PayoutRequestResource($payout->load('bankAccount'))]);
    }
}
