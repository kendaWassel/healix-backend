<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTask extends Model
{
    protected $table = "delivery_tasks";
    protected $fillable = [
        'order_id',
        'delivery_id',
        'status',
        'assigned_at',
        'picked_at',
        'delivered_at',
        'delivery_fee'
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