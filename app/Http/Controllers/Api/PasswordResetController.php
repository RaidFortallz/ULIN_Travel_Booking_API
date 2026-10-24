<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function forgot(Request $request, PasswordResetService $service): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', Str::lower(trim($data['email'])))->first();

        if ($user && $service->secondsUntilResend($user) === 0) {
            $service->send($user);
        }

        return response()->json([
            'message' => 'If that email is registered, a reset code has been sent.',
        ]);
    }

    public function reset(Request $request, PasswordResetService $service): JsonResponse
    {
        $data = $request->validate(
            [
                'email' => ['required', 'string', 'email'],
                'code' => ['required', 'digits:'.PasswordResetService::LENGTH],
                'password' => [
                    'required',
                    'string',
                    'max:72',
                    Password::min(8)->numbers()->symbols(),
                    'regex:/^[A-Z]/',
                ],
            ],
            ['password.regex' => 'The password must start with a capital letter.']
        );

        $user = User::where('email', Str::lower(trim($data['email'])))->first();

        $result = $user
            ? $service->reset($user, $data['code'], $data['password'])
            : 'invalid';

        return match ($result) {
            'reset' => response()->json(['message' => 'Password updated. Please log in.']),
            'expired' => response()->json([
                'message' => 'The code has expired. Request a new one.',
                'code' => 'code_expired',
            ], 422),
            'locked' => response()->json([
                'message' => 'Too many attempts. Request a new code.',
                'code' => 'code_locked',
            ], 429),
            default => response()->json([
                'message' => 'The code is incorrect.',
                'code' => 'code_invalid',
            ], 422),
        };
    }
}