<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single password-reset OTP challenge.
 *
 * Holds only hashes of the OTP and the follow-up reset token; the plaintext of
 * each exists once, in memory, long enough to be emailed or returned.
 */
class PasswordResetOtp extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'otp_hash',
        'expires_at',
        'verified_at',
        'attempts',
        'reset_token_hash',
        'reset_token_expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'reset_token_expires_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Never let a hash reach a JSON response by accident.
     */
    protected $hidden = [
        'otp_hash',
        'reset_token_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ---------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------- */

    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    /** OTPs still inside their validity window. */
    public function scopeUnexpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /** OTPs past their window — the pruning target. */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /* ---------------------------------------------------------------
     | State
     |--------------------------------------------------------------- */

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function hasAttemptsLeft(int $max): bool
    {
        return $this->attempts < $max;
    }

    /**
     * The reset token is valid only if one was issued, it has not expired, and
     * the OTP behind it was actually verified.
     */
    public function hasLiveResetToken(): bool
    {
        return $this->reset_token_hash !== null
            && $this->reset_token_expires_at !== null
            && $this->reset_token_expires_at->isFuture()
            && $this->isVerified();
    }
}
