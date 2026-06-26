<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReleaseDeliveryAssignmentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $taskId,
        public int $deliveryId,
        public int $assignedAtTimestamp,
    ) {}

    public function handle(): void
    {
        //
    }
}
