<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['nullable', 'in:male,female'],
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $user->profile()->updateOrCreate([], $data);

        return response()->json([
            'message' => 'Profile saved.',
            'data' => ['user' => new UserResource($user->fresh())],
        ]);
    }

    public function avatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $profile = $user->profile;

        if (! $profile) {
            return response()->json([
                'message' => 'Complete your profile first.',
            ], 409);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        if ($profile->avatar_path) {
            Storage::disk('public')->delete($profile->avatar_path);
        }

        $profile->update(['avatar_path' => $path]);

        return response()->json([
            'message' => 'Avatar updated.',
            'data' => ['user' => new UserResource($user->fresh())],
        ]);
    }
}