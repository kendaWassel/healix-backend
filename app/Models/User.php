<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens,HasFactory, Notifiable;
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function patient()
    {
        return $this->hasOne(Patient::class); 
    }
    public function doctor() { 
        return $this->hasOne(Doctor::class); 
    }
    public function pharmacist() { 
        return $this->hasOne(Pharmacist::class); 
    }
    public function careProvider() { 
        return $this->hasOne(CareProvider::class); 
    }
    public function delivery() { 
        return $this->hasOne(Delivery::class); 
    }
}
