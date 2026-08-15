<?php

namespace App\Http\Controllers\Api;

use App\Models\DeliveryTask;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\DeliveryAssignmentService;

class OrderController extends Controller
{
    public function __construct(
        protected DeliveryAssignmentService $deliveryAssignmentService
    ) {}

    /**
        * Mark order as ready for delivery
        * POST /api/pharmacist/orders/{order_id}/ready
     */
    public function markReadyForDelivery($orderId)
    {
        try {
            $order = Order::find($orderId);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('pharmacy.order_not_found')
                ], 404);
            }

            if ($order->status !== 'accepted') {
                return response()->json([
                    'status' => 'error',
                    'message' => __('pharmacy.order_must_be_accepted')
                ], 422);
            }

            $order->status = 'ready_for_delivery';
            $order->save();

            // Create the delivery task and offer it to the nearest available
            // driver. The task stays unassigned (status=pending, delivery_id
            // null) until a candidate accepts.
            $task = DeliveryTask::create([
                'order_id' => $order->id,
                'status' => 'pending',
            ]);

            $this->deliveryAssignmentService->assignNearestDriver($task);

            return response()->json([
                'status' => 'success',
                'message' => __('pharmacy.order_ready'),
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'delivery_task_id' => $task->id,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('pharmacy.order_ready_failed'),
                'error' => $e->getMessage()
            ], 500);
        }

    }

}
