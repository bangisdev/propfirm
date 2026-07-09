<?php

namespace App\Services\Kyc;

use App\Models\KycSubmission;
use App\Models\User;
use App\Notifications\Kyc\KycApprovedNotification;
use App\Notifications\Kyc\KycRejectedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KycService
{
    /**
     * Stores documents on the PRIVATE disk (never public — identity documents
     * are among the most sensitive data this platform handles) and creates a
     * pending submission, moving the user's kyc_status to 'pending'.
     */
    public function submit(
        User $user,
        string $documentType,
        UploadedFile $front,
        ?UploadedFile $back,
        ?UploadedFile $selfie,
    ): KycSubmission {
        if ($user->kyc_status === 'pending') {
            throw ValidationException::withMessages(['kyc' => 'You already have a submission under review.']);
        }

        $directory = "kyc/{$user->id}";

        return DB::transaction(function () use ($user, $documentType, $front, $back, $selfie, $directory) {
            $submission = KycSubmission::create([
                'user_id' => $user->id,
                'document_type' => $documentType,
                'document_front_path' => $front->store($directory, 'local'),
                'document_back_path' => $back?->store($directory, 'local'),
                'selfie_path' => $selfie?->store($directory, 'local'),
                'status' => 'pending',
            ]);

            $user->update(['kyc_status' => 'pending']);

            return $submission;
        });
    }

    public function approve(KycSubmission $submission, User $reviewer): KycSubmission
    {
        if ($submission->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending submissions can be approved.']);
        }

        DB::transaction(function () use ($submission, $reviewer) {
            $submission->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            $submission->user->update(['kyc_status' => 'verified', 'kyc_verified_at' => now()]);
        });

        $submission->user->notify(new KycApprovedNotification());

        return $submission->fresh();
    }

    public function reject(KycSubmission $submission, User $reviewer, string $reason): KycSubmission
    {
        if ($submission->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending submissions can be rejected.']);
        }

        DB::transaction(function () use ($submission, $reviewer, $reason) {
            $submission->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            $submission->user->update(['kyc_status' => 'rejected']);
        });

        $submission->user->notify(new KycRejectedNotification($reason));

        return $submission->fresh();
    }
}
