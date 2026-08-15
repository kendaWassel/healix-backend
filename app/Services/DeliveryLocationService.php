<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\DeliveryTask;
use App\Models\User;

class DeliveryLocationService
{
    // Task statuses that accept live GPS updates from the driver app
    public const TRACKABLE_TASK_STATUSES = [
        'picking_up_the_order',
        'on_the_way',
    ];

    /**
     * Find a delivery task assigned to the given driver.
     */
    public function getDeliveryTaskForDelivery(int $taskId, int $deliveryId): ?DeliveryTask
    {
        return DeliveryTask::where('id', $taskId)
            ->where('delivery_id', $deliveryId)
            ->first();
    }

    /**
     * Whether the task is in a status that allows location updates.
     */
    public function taskAllowsLocationUpdates(DeliveryTask $task): bool
    {
        return in_array($task->status, self::TRACKABLE_TASK_STATUSES, true);
    }

    /**
     * Persist the latest coordinates on the driver profile and task location row.
     * Uses updateOrCreate so each task keeps a single location record.
     */
    public function updateOrCreateLatestLocation(DeliveryTask $task, float $latitude, float $longitude): DeliveryLocation
    {
        $task->loadMissing('delivery');

        if ($task->delivery) {
            $task->delivery->update([
                'current_latitude' => $latitude,
                'current_longitude' => $longitude,
            ]);
        }

        return DeliveryLocation::updateOrCreate(
            ['task_id' => $task->id],
            [
                'delivery_id' => $task->delivery_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]
        );
    }

    /**
     * Persist the live position of a driver who isn't on an active task —
     * used while idle/available so the nearest-driver search has somewhere
     * current to read from. No delivery_locations row is written, since
     * that table is scoped to a specific task's tracking history.
     */
    public function updateIdleDriverLocation(Delivery $delivery, float $latitude, float $longitude): void
    {
        $delivery->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
        ]);
    }

    /**
     * Return the latest stored location for a delivery task.
     */
    public function getLatestLocationForTask(int $taskId): ?DeliveryLocation
    {
        return DeliveryLocation::where('task_id', $taskId)->first();
    }

    /**
     * Find a delivery task linked to the authenticated patient's order.
     */
    public function getTaskForPatient(int $taskId, int $patientUserId): ?DeliveryTask
    {
        return DeliveryTask::where('id', $taskId)
            ->whereHas('order.patient.user', function ($query) use ($patientUserId) {
                $query->where('id', $patientUserId);
            })
            ->first();
    }

    /**
     * Resolve a delivery task the authenticated user is allowed to track.
     * Supports delivery drivers (assigned task) and patients (own order).
     */
    public function getAuthorizedTaskForTracking(int $taskId, User $user): ?DeliveryTask
    {
        if ($user->delivery) {
            return $this->getDeliveryTaskForDelivery($taskId, $user->delivery->id);
        }

        if ($user->patient) {
            return $this->getTaskForPatient($taskId, $user->id);
        }

        return null;
    }

    /**
     * Not-found message tailored to the authenticated user's role.
     */
    public function getTaskNotFoundMessageForUser(User $user): string
    {
        if ($user->delivery) {
            return 'Task not found or not assigned to this delivery user.';
        }

        if ($user->patient) {
            return 'Task not found for this patient.';
        }

        return 'Task not found.';
    }
}
