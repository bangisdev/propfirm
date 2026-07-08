<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Challenge;
use App\Models\Order;
use App\Services\Payments\OrderService;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentFulfillmentService $fulfillment,
    ) {}

    /**
     * POST /api/v1/checkout
     * Creates a pending order and returns the Paystack authorization URL to redirect to.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $challenge = Challenge::findOrFail($request->input('challenge_id'));

        if (! $challenge->is_active) {
            throw ValidationException::withMessages(['challenge_id' => 'This challenge is no longer available.']);
        }

        [$order, $authorizationUrl] = $this->orders->checkout(
            user: $request->user(),
            challenge: $challenge,
            couponCode: $request->input('coupon_code'),
            callbackUrl: config('app.frontend_url').'/dashboard/challenges/checkout/callback',
        );

        return response()->json([
            'data' => [
                'order' => new OrderResource($order->load('challenge')),
                'authorization_url' => $authorizationUrl, // null when a 100%-off coupon skipped the gateway
            ],
        ], 201);
    }

    /**
     * GET /api/v1/checkout/{reference}
     * Polled by the frontend after the Paystack redirect returns the user to our callback URL.
     * Also serves as a manual reconciliation path if the webhook is delayed.
     */
    public function show(string $reference, Request $request): JsonResponse
    {
        $order = Order::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($order->status === 'pending' && ! $order->isExpired()) {
            $order = $this->fulfillment->verifyAndFulfill($reference);
        }

        return response()->json(['data' => new OrderResource($order->load('challenge'))]);
    }

    /**
     * GET /api/v1/orders — order history for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with('challenge')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}
