<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        return response()->json([
            'message' => 'Registration successful.',
            'data' => ['user' => new UserResource($user)],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', Str::lower(trim($data['email'])))->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'The email or password is incorrect.',
            ], 401);
        }

        return $this->tokenResponse($user, $data['device_name'] ?? null);
    }

    public function google(Request $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $payload = $verifier->verify($data['id_token']);

        if (! $payload) {
            return response()->json(['message' => 'Google sign-in failed.'], 401);
        }

        $email = Str::lower($payload['email']);

        $user = User::where('google_id', $payload['sub'])->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            if ($user->google_id && $user->google_id !== $payload['sub']) {
                return response()->json(['message' => 'Google sign-in failed.'], 401);
            }

            $changes = [];

            if (! $user->google_id) {
                $changes['google_id'] = $payload['sub'];
            }

            if (! $user->hasVerifiedEmail()) {
                $changes['email_verified_at'] = now();
                $changes['password'] = null;
                $user->tokens()->delete();
            }

            if ($changes) {
                $user->forceFill($changes)->save();
            }
        } else {
            $user = new User();
            $user->forceFill([
                'name' => $payload['name'] ?? Str::before($email, '@'),
                'email' => $email,
                'google_id' => $payload['sub'],
                'email_verified_at' => now(),
            ])->save();
        }

        return $this->tokenResponse($user, $data['device_name'] ?? null);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['user' => new UserResource($request->user())],
        ]);
    }

    private function tokenResponse(User $user, ?string $deviceName): JsonResponse
    {
        $token = $user->createToken(
            $deviceName ?: 'mobile',
            ['*'],
            now()->addDays(30)
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ]);
    }
}