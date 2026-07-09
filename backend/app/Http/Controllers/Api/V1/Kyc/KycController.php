<?php

namespace App\Http\Controllers\Api\V1\Kyc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kyc\SubmitKycRequest;
use App\Http\Resources\KycSubmissionResource;
use App\Models\KycSubmission;
use App\Services\Kyc\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function __construct(private readonly KycService $kyc) {}

    /**
     * GET /api/v1/kyc
     */
    public function show(Request $request): JsonResponse
    {
        $submission = KycSubmission::where('user_id', $request->user()->id)->latest()->first();

        return response()->json([
            'data' => [
                'kyc_status' => $request->user()->kyc_status,
                'latest_submission' => $submission ? new KycSubmissionResource($submission) : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/kyc
     */
    public function store(SubmitKycRequest $request): JsonResponse
    {
        $submission = $this->kyc->submit(
            user: $request->user(),
            documentType: $request->input('document_type'),
            front: $request->file('document_front'),
            back: $request->file('document_back'),
            selfie: $request->file('selfie'),
        );

        return response()->json(['data' => new KycSubmissionResource($submission)], 201);
    }
}
