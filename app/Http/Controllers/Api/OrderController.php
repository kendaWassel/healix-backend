<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
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
                    'message' => 'Order not found'
                ], 404);
            }
            $this->authorize('update', $order);

            if ($order->status !== 'accepted') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order must be accepted before marking as ready for delivery'
                ], 422);
            }

            $order->status = 'ready_for_delivery';
            $order->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Order marked as ready for delivery',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark order as ready for delivery',
                'error' => $e->getMessage()
            ], 500);
        }

    }

}
