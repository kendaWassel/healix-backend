<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;


class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    use HasApiTokens, HasFactory, Notifiable,HasRoles;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'role',
        'preferred_locale',

        // Account approval
        'status',
        'is_active',
        'rejection_reason',
        'admin_note',
        'approved_at',
    ];

    /**
     * Hidden attributes
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at'       => 'datetime',
            'is_active'         => 'boolean',
            'password'          => 'hashed',
        ];
    }

    /* =======================
     | Relationships
     ======================= */

    public function patient()
    {
        return $this->hasOne(Patient::class);
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class);
    }

    public function pharmacist()
    {
        return $this->hasOne(Pharmacist::class);
    }

    public function careProvider()
    {
        return $this->hasOne(CareProvider::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }
    public function uploads()
    {
        return $this->hasMany(Upload::class);
    }

    /* =======================
     | Scopes 
     ======================= */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /* =======================
     | Helpers
     ======================= */

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Password reset OTPs issued to this user.
     *
     * Note there is no sendPasswordResetNotification() override: the flow no
     * longer goes through Laravel's password broker at all. Codes are issued
     * and verified by App\Services\Auth\PasswordResetOtpService.
     */
    public function passwordResetOtps()
    {
        return $this->hasMany(PasswordResetOtp::class);
    }

    /**
     * Locale Laravel should use when rendering notifications for this user.
     *
     * Queued notifications run on a worker with no HTTP request, so the
     * Accept-Language header is long gone by then; this stored preference is
     * what keeps reminder mails and SMS in the recipient's own language.
     */
    public function preferredLocale(): ?string
    {
        $supported = (array) config('localization.supported', ['en']);

        return in_array($this->preferred_locale, $supported, true)
            ? $this->preferred_locale
            : (string) config('localization.default', 'en');
    }

    //google account relationship
    public function googleAccount()
    {
        return $this->hasOne(GoogleAccount::class);
    }
}
