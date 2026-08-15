<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryTask;
use App\Models\DeliveryTaskCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Point-to-point delivery assignment: at any time a task has at most one
 * pending candidate. If that candidate rejects or their response window
 * expires, the task is offered to the next-nearest available driver who
 * hasn't already been tried. The search radius only escalates when a step
 * finds nobody at all within it — it never fans a task out to multiple
 * drivers at once.
 */
class DeliveryAssignmentService
{
    protected OSRMService $osrmService;

    public function __construct(OSRMService $osrmService)
    {
        $this->osrmService = $osrmService;
    }

    /**
     * Offer the task to the single nearest available driver who hasn't
     * already been tried for it, escalating the search radius as needed.
     * Returns the new candidate, or null if nobody could be found at all.
     */
    public function assignNearestDriver(DeliveryTask $task): ?DeliveryTaskCandidate
    {
        return DB::transaction(function () use ($task) {
            $task = DeliveryTask::where('id', $task->id)->lockForUpdate()->first();

            if (!$task || $task->status !== 'pending' || $task->delivery_id) {
                return null;
            }

            $task->loadMissing('order.pharmacist');
            $pharmacy = $task->order->pharmacist;

            if (!$pharmacy || !$pharmacy->latitude || !$pharmacy->longitude) {
                return null;
            }

            $alreadyTriedIds = DeliveryTaskCandidate::where('task_id', $task->id)
                ->pluck('delivery_id')
                ->all();

            $nearest = $this->findNearestUntried(
                (float) $pharmacy->latitude,
                (float) $pharmacy->longitude,
                $alreadyTriedIds
            );

            if (!$nearest) {
                return null;
            }

            return DeliveryTaskCandidate::create([
                'task_id' => $task->id,
                'delivery_id' => $nearest->id,
                'status' => 'pending',
                'sent_at' => now(),
            ]);
        });
    }

    /**
     * Search progressively wider radii (config('delivery.radius_expansion'))
     * for the single nearest available driver not already in $excludeIds,
     * stopping at the first radius that finds anyone.
     */
    protected function findNearestUntried(float $lat, float $lng, array $excludeIds): ?Delivery
    {
        foreach (config('delivery.radius_expansion', []) as $radiusKm) {
            $drivers = $this->findNearbyAvailableDrivers($lat, $lng, (float) $radiusKm, $excludeIds);

            if ($drivers->isNotEmpty()) {
                return $this->sortDriversByEta($drivers, $lat, $lng)->first();
            }
        }

        return null;
    }

    /**
     * If the task's current candidate has been pending longer than the
     * configured response timeout, expire it and move on to the next
     * nearest driver. Called opportunistically from the endpoints that
     * touch a task, since no queue worker runs in this deployment.
     */
    public function expireStaleCandidateAndReassign(DeliveryTask $task): void
    {
        if ($task->status !== 'pending' || $task->delivery_id) {
            return;
        }

        $timeoutSeconds = (int) config('delivery.broadcast_timeout_seconds', 30);

        $stale = DeliveryTaskCandidate::where('task_id', $task->id)
            ->where('status', 'pending')
            ->where('sent_at', '<=', now()->subSeconds($timeoutSeconds))
            ->first();

        if (!$stale) {
            return;
        }

        $stale->update(['status' => 'expired', 'responded_at' => now()]);

        $this->assignNearestDriver($task);
    }

    /**
     * @return array{task: DeliveryTask, route: ?array}|null
     */
    public function acceptTask(DeliveryTask $task, Delivery $delivery): ?array
    {
        return DB::transaction(function () use ($task, $delivery) {
            $task = DeliveryTask::where('id', $task->id)->lockForUpdate()->first();

            if (!$task || $task->status !== 'pending') {
                return null;
            }

            if ($task->delivery_id) {
                return ['conflict' => true];
            }

            $candidate = DeliveryTaskCandidate::where('task_id', $task->id)
                ->where('delivery_id', $delivery->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$candidate) {
                return null;
            }

            $task->loadMissing('order.pharmacist');
            $pharmacy = $task->order->pharmacist;

            $route = null;
            if ($pharmacy && $pharmacy->latitude && $pharmacy->longitude) {
                $route = $this->osrmService->getRoute(
                    $delivery->current_latitude,
                    $delivery->current_longitude,
                    (float) $pharmacy->latitude,
                    (float) $pharmacy->longitude
                );
            }

            $now = now();

            $task->update([
                'delivery_id' => $delivery->id,
                'assigned_at' => $now,
                'status' => 'picking_up_the_order',
            ]);

            $candidate->update([
                'status' => 'accepted',
                'responded_at' => $now,
            ]);

            $task->order->update(['status' => 'out_for_delivery']);

            return [
                'task' => $task->fresh(),
                'route' => $route,
            ];
        });
    }

    /**
     * Reject the current offer and immediately move on to the next nearest
     * driver, so the patient doesn't wait out the full response timeout.
     */
    public function rejectCandidate(DeliveryTask $task, Delivery $delivery): bool
    {
        $rejected = DB::transaction(function () use ($task, $delivery) {
            $candidate = DeliveryTaskCandidate::where('task_id', $task->id)
                ->where('delivery_id', $delivery->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$candidate) {
                return false;
            }

            $candidate->update([
                'status' => 'rejected',
                'responded_at' => now(),
            ]);

            return true;
        });

        if ($rejected) {
            $this->assignNearestDriver($task);
        }

        return $rejected;
    }

    public function getBusyDeliveryIds(): array
    {
        return DeliveryTask::whereIn('status', ['picking_up_the_order', 'on_the_way'])
            ->whereNotNull('delivery_id')
            ->pluck('delivery_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $excludeDeliveryIds
     */
    public function findNearbyAvailableDrivers(
        float $latitude,
        float $longitude,
        float $radiusKm,
        array $excludeDeliveryIds = []
    ): Collection {
        $excludeDeliveryIds = array_values(array_unique(array_merge(
            $excludeDeliveryIds,
            $this->getBusyDeliveryIds()
        )));

        $earthRadiusKm = 6371;

        $query = Delivery::query()
            ->select('deliveries.*')
            ->selectRaw(
                "(? * acos(
                    cos(radians(?)) * cos(radians(current_latitude)) *
                    cos(radians(current_longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(current_latitude))
                )) AS distance_km",
                [$earthRadiusKm, $latitude, $longitude, $latitude]
            )
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->having('distance_km', '<=', $radiusKm);

        if (!empty($excludeDeliveryIds)) {
            $query->whereNotIn('deliveries.id', $excludeDeliveryIds);
        }

        return $query->with('user')->get();
    }

    public function sortDriversByEta(Collection $drivers, float $latitude, float $longitude): Collection
    {
        return $drivers
            ->map(function (Delivery $driver) use ($latitude, $longitude) {
                $route = $this->osrmService->getRoute(
                    $driver->current_latitude,
                    $driver->current_longitude,
                    $latitude,
                    $longitude
                );

                $driver->eta_seconds = $route['duration_seconds'] ?? PHP_INT_MAX;

                return $driver;
            })
            ->sortBy('eta_seconds')
            ->values();
    }
}
