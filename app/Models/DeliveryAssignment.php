<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAssignment extends Model
{
    protected $table = "delivery_assignments";
    protected $fillable = [
        'order_id',
        'delivery_id',
        'status',
        'assigned_at',
        'delivered_at'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }
}