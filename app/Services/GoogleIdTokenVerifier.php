<?php

namespace App\Services;

use Google\Auth\AccessToken;
use Throwable;

class GoogleIdTokenVerifier
{
    public function verify(string $idToken): ?array
    {
        $clientId = config('services.google.client_id');

        if (blank($clientId)) {
            return null;
        }

        try {
            $payload = (new AccessToken())->verify($idToken, [
                'audience' => $clientId,
            ]);
        } catch (Throwable $e) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $issuers = ['accounts.google.com', 'https://accounts.google.com'];

        if (! in_array($payload['iss'] ?? '', $issuers, true)) {
            return null;
        }

        if (empty($payload['sub']) || empty($payload['email'])) {
            return null;
        }

        if (! filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return $payload;
    }
}