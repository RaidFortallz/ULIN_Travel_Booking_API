<?php

namespace App\Services;

use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class PasswordResetService
{
    public const LENGTH = 6;
    public const TTL_MINUTES = 10;
    public const RESEND_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    public function send(User $user): void
    {
        $code = str_pad(
            (string) random_int(0, (10 ** self::LENGTH) - 1),
            self::LENGTH,
            '0',
            STR_PAD_LEFT
        );

        PasswordResetCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => $this->hash($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'sent_at' => now(),
            ]
        );

        Mail::to($user->email)->send(
            new PasswordResetCodeMail($user, $code, self::TTL_MINUTES)
        );
    }

    public function secondsUntilResend(User $user): int
    {
        $record = $user->passwordResetCode;

        if (! $record) {
            return 0;
        }

        $elapsed = now()->timestamp - $record->sent_at->timestamp;

        return max(0, self::RESEND_SECONDS - $elapsed);
    }

    public function reset(User $user, string $code, string $password): string
    {
        $record = $user->passwordResetCode;

        if (! $record) {
            return 'missing';
        }

        if ($record->expires_at->isPast()) {
            return 'expired';
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            return 'locked';
        }

        if (! hash_equals($record->code_hash, $this->hash($code))) {
            $record->increment('attempts');

            return 'invalid';
        }

        $user->forceFill(['password' => $password]);

        if (! $user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
        }

        $user->save();
        $user->tokens()->delete();
        $record->delete();

        return 'reset';
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}