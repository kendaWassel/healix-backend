<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'role',

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

    /* =======================
     | Scopes (مفيدة جدًا)
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
}
