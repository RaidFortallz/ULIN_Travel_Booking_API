<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class EmailVerificationService
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

        EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => $this->hash($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'sent_at' => now(),
            ]
        );

        Mail::to($user->email)->send(
            new VerificationCodeMail($user, $code, self::TTL_MINUTES)
        );
    }

    public function secondsUntilResend(User $user): int
    {
        $record = $user->verificationCode;

        if (! $record) {
            return 0;
        }

        $elapsed = now()->timestamp - $record->sent_at->timestamp;

        return max(0, self::RESEND_SECONDS - $elapsed);
    }

    public function verify(User $user, string $code): string
    {
        $record = $user->verificationCode;

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

        $user->markEmailAsVerified();
        $record->delete();

        return 'verified';
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}