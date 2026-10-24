<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function send(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.'], 409);
        }

        $wait = $verification->secondsUntilResend($user);

        if ($wait > 0) {
            return response()->json([
                'message' => 'Please wait before requesting a new code.',
                'retry_after' => $wait,
            ], 429);
        }

        $verification->send($user);

        return response()->json([
            'message' => 'Verification code sent.',
            'data' => [
                'expires_in' => EmailVerificationService::TTL_MINUTES * 60,
                'resend_in' => EmailVerificationService::RESEND_SECONDS,
            ],
        ]);
    }

    public function verify(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.'], 409);
        }

        $data = $request->validate([
            'code' => ['required', 'digits:'.EmailVerificationService::LENGTH],
        ]);

        return match ($verification->verify($user, $data['code'])) {
            'verified' => response()->json([
                'message' => 'Email verified.',
                'data' => ['user' => new UserResource($user->fresh())],
            ]),
            'expired' => response()->json([
                'message' => 'The code has expired. Request a new one.',
                'code' => 'code_expired',
            ], 422),
            'locked' => response()->json([
                'message' => 'Too many attempts. Request a new code.',
                'code' => 'code_locked',
            ], 429),
            'missing' => response()->json([
                'message' => 'No code has been requested.',
                'code' => 'code_missing',
            ], 422),
            default => response()->json([
                'message' => 'The code is incorrect.',
                'code' => 'code_invalid',
            ], 422),
        };
    }
}