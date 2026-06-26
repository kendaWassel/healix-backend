<?php

namespace App\Services;

use App\Jobs\ExpandDeliverySearchJob;
use App\Models\Delivery;
use App\Models\DeliveryTask;
use App\Models\DeliveryTaskCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryAssignmentService
{
    protected OSRMService $osrmService;

    public function __construct(OSRMService $osrmService)
    {
        $this->osrmService = $osrmService;
    }

    /**
     * Broadcast a pending task to nearby drivers at the first configured radius.
     */
    public function broadcastTask(DeliveryTask $task): array
    {
        $radiusIndex = 0;
        $radiusKm = $this->radiusAtIndex($radiusIndex);
        $candidatesCreated = $this->broadcastAtRadius($task, $radiusKm);

        $this->scheduleRadiusExpansion($task, $radiusIndex);

        return [
            'task_id' => $task->id,
            'radius_km' => $radiusKm,
            'candidates_count' => $candidatesCreated,
        ];
    }

    /**
     * Invite nearby drivers who have never received this task before.
     */
    public function broadcastAtRadius(DeliveryTask $task, float $radiusKm): int
    {
        $task->loadMissing('order.pharmacist');

        $pharmacy = $task->order->pharmacist;
        if (!$pharmacy || !$pharmacy->latitude || !$pharmacy->longitude) {
            return 0;
        }

        $alreadyInvitedIds = DeliveryTaskCandidate::where('task_id', $task->id)
            ->pluck('delivery_id')
            ->all();

        $drivers = $this->findNearbyAvailableDrivers(
            (float) $pharmacy->latitude,
            (float) $pharmacy->longitude,
            $radiusKm,
            $alreadyInvitedIds
        );

        if ($drivers->isEmpty()) {
            return 0;
        }

        $sortedDrivers = $this->sortDriversByEta(
            $drivers,
            (float) $pharmacy->latitude,
            (float) $pharmacy->longitude
        );

        return $this->createCandidates($task, $sortedDrivers);
    }

    public function expirePendingCandidates(DeliveryTask $task): int
    {
        return DeliveryTaskCandidate::where('task_id', $task->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'expired',
                'responded_at' => now(),
            ]);
    }

    public function expandSearch(DeliveryTask $task, int $currentRadiusIndex): ?array
    {
        if ($task->delivery_id || $task->status !== 'pending') {
            return null;
        }

        $this->expirePendingCandidates($task);

        $nextRadiusIndex = $currentRadiusIndex + 1;
        $nextRadiusKm = $this->radiusAtIndex($nextRadiusIndex);

        if ($nextRadiusKm === null) {
            return null;
        }

        $candidatesCreated = $this->broadcastAtRadius($task, $nextRadiusKm);

        if ($this->radiusAtIndex($nextRadiusIndex + 1) !== null) {
            $this->scheduleRadiusExpansion($task, $nextRadiusIndex);
        }

        return [
            'task_id' => $task->id,
            'radius_km' => $nextRadiusKm,
            'candidates_count' => $candidatesCreated,
        ];
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

            DeliveryTaskCandidate::where('task_id', $task->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'expired',
                    'responded_at' => $now,
                ]);

            $task->order->update(['status' => 'out_for_delivery']);

            return [
                'task' => $task->fresh(),
                'route' => $route,
            ];
        });
    }

    public function rejectCandidate(DeliveryTask $task, Delivery $delivery): bool
    {
        return DB::transaction(function () use ($task, $delivery) {
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

    protected function createCandidates(DeliveryTask $task, Collection $drivers): int
    {
        if ($drivers->isEmpty()) {
            return 0;
        }

        $now = now();
        $records = $drivers->map(fn (Delivery $driver) => [
            'task_id' => $task->id,
            'delivery_id' => $driver->id,
            'status' => 'pending',
            'sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DeliveryTaskCandidate::insert($records);

        return count($records);
    }

    protected function scheduleRadiusExpansion(DeliveryTask $task, int $currentRadiusIndex): void
    {
        if ($this->radiusAtIndex($currentRadiusIndex + 1) === null) {
            return;
        }

        ExpandDeliverySearchJob::dispatch($task->id, $currentRadiusIndex)
            ->delay(now()->addSeconds((int) config('delivery.broadcast_expansion_delay_seconds')));
    }

    protected function radiusAtIndex(int $index): ?float
    {
        $radii = config('delivery.radius_expansion', []);
        $maxRadius = (float) config('delivery.max_radius_km');

        if (!isset($radii[$index])) {
            return null;
        }

        $radius = (float) $radii[$index];

        return $radius <= $maxRadius ? $radius : null;
    }
}
