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
        'driving_license_id',
        'gender',
        'rating_avg',
        'current_latitude',
        'current_longitude',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function deliveryImage()
    {
        return $this->belongsTo(Upload::class, 'delivery_image_id');
    }
    public function drivingLicense()
    {
        return $this->belongsTo(Upload::class, 'driving_license_id');
    }
}
