<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'prescription_id',
        'pharmacy_id',
        'patient_id',
        'status',
        'delivered_at',
        'delivery_method',
        'rejection_reason',  
    ];
    
    protected $dates = [
        'delivered_at',
    ];
    
    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function delivery()
    {
        return $this->hasOne(DeliveryAssignment::class, 'order_id');
    }
}
