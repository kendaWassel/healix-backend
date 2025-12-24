<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMedication extends Model
{
    protected $fillable = [
        'order_id',
        'medication_id',
        'total_quantity',
        'total_price',
    ];


    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function medication()
    {
        return $this->belongsTo(Medication::class);
    }
    
}
