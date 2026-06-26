<?php

namespace App\Jobs;

use App\Models\DeliveryTask;
use App\Services\DeliveryAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpandDeliverySearchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $taskId,
        public int $currentRadiusIndex,
    ) {}

    public function handle(DeliveryAssignmentService $deliveryAssignmentService): void
    {
        $task = DeliveryTask::find($this->taskId);

        if (!$task || $task->status !== 'pending' || $task->delivery_id) {
            return;
        }

        $deliveryAssignmentService->expandSearch($task, $this->currentRadiusIndex);
    }
}
