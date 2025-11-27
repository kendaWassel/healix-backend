<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;
    protected $table = 'deliveries';
    protected $fillable=[
        'user_id',
        'delivery_image_id',
        'vehicle_type',
        'plate_number',
        'bank_account',
        'driving_license_id',
        'rating_avg'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function order(){
        return $this->belongsTo(Order::class);
    }
    public function deliveryAssignments(){
        return $this->hasMany(DeliveryAssignment::class);
    }
}
