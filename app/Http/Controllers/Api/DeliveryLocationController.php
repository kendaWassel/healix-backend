<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryLocationUpdateRequest;
use App\Http\Resources\DeliveryLocationResource;
use App\Services\DeliveryLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DeliveryLocationController extends Controller
{
    protected DeliveryLocationService $service;

    public function __construct(DeliveryLocationService $service)
    {
        $this->service = $service;
    }

    /**
     * POST /api/delivery/location/update
     *
     * Driver app sends GPS coordinates every few seconds. While idle/
     * available (no task_id, or no active task), this just keeps the
     * driver's current position current for the nearest-driver search.
     * While on an active task, it also records a per-task tracking point
     * the patient can poll.
     */
    public function updateLocation(DeliveryLocationUpdateRequest $request): JsonResponse
    {
        $delivery = Auth::user()->delivery;

        if (!$delivery) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.unauthorized'),
            ], 403);
        }

        $task = $request->filled('task_id')
            ? $this->service->getDeliveryTaskForDelivery((int) $request->task_id, $delivery->id)
            : null;

        if (!$task) {
            $this->service->updateIdleDriverLocation(
                $delivery,
                (float) $request->latitude,
                (float) $request->longitude
            );

            return response()->json([
                'status' => 'success',
                'message' => __('delivery.location_updated'),
                'data' => null,
            ]);
        }

        if (!$this->service->taskAllowsLocationUpdates($task)) {
            return response()->json([
                'status' => 'error',
                'message' => __('delivery.location_not_allowed'),
            ], 422);
        }

        $location = $this->service->updateOrCreateLatestLocation(
            $task,
            (float) $request->latitude,
            (float) $request->longitude
        );

        $this->service->notifyIfDriverNearby(
            $task,
            (float) $request->latitude,
            (float) $request->longitude
        );

        return response()->json([
            'status' => 'success',
            'message' => __('delivery.location_updated'),
            'data' => new DeliveryLocationResource($location),
        ]);
    }

    /**
     * Poll the latest driver coordinates for a delivery task.
     *
     * GET /api/delivery/location/{task_id}         delivery drivers
     * GET /api/patient/delivery/location/{task_id} patients
     */
    public function getLocation($taskId): JsonResponse
    {
        $user = Auth::user();

        if (!$user->delivery && !$user->patient) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.unauthorized'),
            ], 403);
        }

        $task = $this->service->getAuthorizedTaskForTracking((int) $taskId, $user);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => $this->service->getTaskNotFoundMessageForUser($user),
            ], 404);
        }

        $location = $this->service->getLatestLocationForTask($task->id);

        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => __('delivery.location_unavailable'),
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new DeliveryLocationResource($location),
        ]);
    }
}
