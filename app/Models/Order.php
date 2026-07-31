<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'prescription_id',
        'pharmacist_id',
        'patient_id',
        'status',
        // Without this the Stripe webhook's update(['payment_status' => 'paid'])
        // is silently dropped by mass-assignment protection: the customer is
        // charged but the order stays "pending" forever.
        'payment_status',
        'rejection_reason',
        'total_amount',
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

    public function deliveryTask()
    {
        return $this->hasOne(DeliveryTask::class, 'order_id');
    }

}
