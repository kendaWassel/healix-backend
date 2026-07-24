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
     * Driver app sends GPS coordinates every few seconds while actively delivering.
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

        $task = $this->service->getDeliveryTaskForDelivery($request->task_id, $delivery->id);

        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => __('delivery.task_not_assigned'),
            ], 404);
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
