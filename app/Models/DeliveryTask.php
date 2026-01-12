<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class DeliveryTask extends Model
{
    use HasFactory;
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
    protected $casts = [
        'assigned_at' => 'datetime',
        'picked_at' => 'datetime',
        'delivered_at' => 'datetime'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }
    public function ratings(){
        return $this->hasMany(Rating::class, 'delivery_task_id');
    }
}