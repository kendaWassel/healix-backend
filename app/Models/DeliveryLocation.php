<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryLocation extends Model
{
    use HasFactory;

    /** Seconds since the last update before a driver is considered offline. */
    public const ONLINE_THRESHOLD_SECONDS = 20;

    protected $table = 'delivery_locations';

    protected $fillable = [
        'task_id',
        'delivery_id',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(DeliveryTask::class, 'task_id');
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    /**
     * Whether the driver is considered online based on the latest location timestamp.
     */
    public function isOnline(): bool
    {
        if (!$this->updated_at) {
            return false;
        }

        return $this->updated_at->greaterThanOrEqualTo(
            now()->subSeconds(self::ONLINE_THRESHOLD_SECONDS)
        );
    }
}
