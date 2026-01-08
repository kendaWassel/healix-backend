<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = [
        'user_id',
        'target_type',
        'target_id',
        'stars',
        'consultation_id',
        'order_id',
        'home_visit_id',
        'delivery_task_id',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function target()
    {
        $modelClass = match($this->target_type) {
            'doctor' => Doctor::class,
            'pharmacist' => Pharmacist::class,
            'care_provider' => CareProvider::class,
            'delivery' => Delivery::class,
        };

        return $modelClass::find($this->target_id);
    }
}
