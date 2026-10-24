<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'has_password' => $this->password !== null,
            'profile_completed' => $profile !== null,
            'profile' => $profile ? [
                'username' => $profile->username,
                'bio' => $profile->bio,
                'phone' => $profile->phone,
                'birthday' => $profile->birthday?->format('Y-m-d'),
                'gender' => $profile->gender,
                'location' => $profile->location,
                'avatar_url' => $profile->avatar_url,
            ] : null,
        ];
    }
}