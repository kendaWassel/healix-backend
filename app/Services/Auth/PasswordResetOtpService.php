<?php

namespace App\Services\Auth;

use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Notifications\ResetPasswordOtpNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OTP-based password reset.
 *
 * Three steps, each of which fails closed:
 *
 *   1. request()  — issue a 6-digit code, email it, store only its hash
 *   2. verify()   — check the code, then trade it for a short-lived reset token
 *   3. reset()    — check the token, set the password, delete every OTP row
 *
 * Callers get booleans and null, never "user not found", so the controller
 * cannot accidentally turn an internal outcome into an enumeration oracle.
 */
class PasswordResetOtpService
{
    public const RESULT_OK = 'ok';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_EXPIRED = 'expired';
    public const RESULT_TOO_MANY_ATTEMPTS = 'too_many_attempts';

    /* ---------------------------------------------------------------
     | Step 1 — request an OTP
     |--------------------------------------------------------------- */

    /**
     * Issue and email an OTP.
     *
     * Returns void deliberately: whether the address exists, whether a
     * cooldown blocked it, and whether mail succeeded are all logged but never
     * surfaced, so every caller gets the identical generic response.
     */
    public function request(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Still burn a comparable amount of time? Not needed here: the
            // response is generic and the endpoint is rate limited, and a fake
            // hash round would not meaningfully close the timing gap against
            // network jitter.
            Log::info('Password reset OTP requested for unknown email', ['email' => $email]);

            return;
        }

        if ($this->withinResendCooldown($email)) {
            Log::info('Password reset OTP suppressed by cooldown', ['email' => $email]);

            return;
        }

        // One live challenge per address: issuing a new code must invalidate
        // any earlier one, otherwise several codes would be valid at once.
        $this->clearFor($email);

        $otp = $this->generateOtp();

        PasswordResetOtp::create([
            'user_id' => $user->id,
            'email' => $email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes($this->expiryMinutes()),
            'attempts' => 0,
        ]);

        try {
            $user->notify(new ResetPasswordOtpNotification($otp, $this->expiryMinutes()));
        } catch (\Throwable $e) {
            Log::error('Failed to send password reset OTP', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /* ---------------------------------------------------------------
     | Step 2 — verify the OTP
     |--------------------------------------------------------------- */

    /**
     * Check an OTP and, on success, issue a reset token.
     *
     * @return array{result: string, reset_token: string|null, expires_in: int|null}
     */
    public function verify(string $email, string $otp): array
    {
        $record = $this->liveRecordFor($email);

        if (! $record) {
            return $this->outcome(self::RESULT_INVALID);
        }

        if ($record->isExpired()) {
            return $this->outcome(self::RESULT_EXPIRED);
        }

        if (! $record->hasAttemptsLeft($this->maxAttempts())) {
            return $this->outcome(self::RESULT_TOO_MANY_ATTEMPTS);
        }

        // An already-verified OTP must not be replayed into a second token.
        if ($record->isVerified()) {
            return $this->outcome(self::RESULT_INVALID);
        }

        if (! Hash::check($otp, $record->otp_hash)) {
            $record->increment('attempts');

            // Burn the challenge outright once the cap is hit, so the remaining
            // keyspace cannot be walked by requesting a fresh window.
            return $record->attempts >= $this->maxAttempts()
                ? $this->outcome(self::RESULT_TOO_MANY_ATTEMPTS)
                : $this->outcome(self::RESULT_INVALID);
        }

        $resetToken = Str::random(64);
        $expiryMinutes = $this->resetTokenExpiryMinutes();

        $record->forceFill([
            'verified_at' => now(),
            'reset_token_hash' => hash('sha256', $resetToken),
            'reset_token_expires_at' => now()->addMinutes($expiryMinutes),
        ])->save();

        return [
            'result' => self::RESULT_OK,
            'reset_token' => $resetToken,
            'expires_in' => $expiryMinutes * 60,
        ];
    }

    /* ---------------------------------------------------------------
     | Step 3 — set the new password
     |--------------------------------------------------------------- */

    /**
     * Consume a reset token and change the password.
     */
    public function reset(string $email, string $resetToken, string $password): string
    {
        $record = $this->liveRecordFor($email);

        if (! $record || ! $record->hasLiveResetToken()) {
            return self::RESULT_INVALID;
        }

        // sha256 + hash_equals rather than Hash::check: the token is 64 random
        // characters, so it needs no key stretching, and this stays constant time.
        if (! hash_equals($record->reset_token_hash, hash('sha256', $resetToken))) {
            return self::RESULT_INVALID;
        }

        $user = $record->user ?? User::where('email', $email)->first();

        if (! $user) {
            return self::RESULT_INVALID;
        }

        DB::transaction(function () use ($user, $password, $email) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            // A password change is the standard response to a suspected
            // compromise: drop every issued Sanctum token so any session an
            // attacker still holds dies with the old password.
            $user->tokens()->delete();

            // No row survives a successful reset, so the OTP and its token
            // cannot be replayed.
            $this->clearFor($email);
        });

        event(new PasswordReset($user));

        return self::RESULT_OK;
    }

    /* ---------------------------------------------------------------
     | Maintenance
     |--------------------------------------------------------------- */

    /**
     * Drop rows whose OTP window has closed. Verified rows are kept until their
     * reset token also expires, so a user mid-flow is not cut off.
     *
     * @return int Rows deleted.
     */
    public function pruneExpired(): int
    {
        return PasswordResetOtp::query()
            ->expired()
            ->where(function ($query) {
                $query->whereNull('reset_token_expires_at')
                    ->orWhere('reset_token_expires_at', '<=', now());
            })
            ->delete();
    }

    /* ---------------------------------------------------------------
     | Internals
     |--------------------------------------------------------------- */

    /** Newest challenge for an address, expired or not. */
    protected function liveRecordFor(string $email): ?PasswordResetOtp
    {
        return PasswordResetOtp::forEmail($email)->latest('id')->first();
    }

    protected function clearFor(string $email): void
    {
        PasswordResetOtp::forEmail($email)->delete();
    }

    protected function withinResendCooldown(string $email): bool
    {
        $cooldown = (int) config('password_otp.resend_cooldown_seconds', 60);

        if ($cooldown <= 0) {
            return false;
        }

        return PasswordResetOtp::forEmail($email)
            ->where('created_at', '>', now()->subSeconds($cooldown))
            ->exists();
    }

    /**
     * Cryptographically secure zero-padded code, e.g. "042317".
     */
    protected function generateOtp(): string
    {
        $length = (int) config('password_otp.length', 6);

        return str_pad(
            (string) random_int(0, (10 ** $length) - 1),
            $length,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * @return array{result: string, reset_token: null, expires_in: null}
     */
    protected function outcome(string $result): array
    {
        return ['result' => $result, 'reset_token' => null, 'expires_in' => null];
    }

    protected function expiryMinutes(): int
    {
        return (int) config('password_otp.expiry_minutes', 10);
    }

    protected function maxAttempts(): int
    {
        return (int) config('password_otp.max_attempts', 5);
    }

    protected function resetTokenExpiryMinutes(): int
    {
        return (int) config('password_otp.reset_token_expiry_minutes', 15);
    }
}
