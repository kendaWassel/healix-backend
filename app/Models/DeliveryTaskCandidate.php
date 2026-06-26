<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryTaskCandidate extends Model
{
    protected $fillable = [
        'task_id',
        'delivery_id',
        'status',
        'sent_at',
        'responded_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(DeliveryTask::class, 'task_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }
}
