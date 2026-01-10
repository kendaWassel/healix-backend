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
            $user = Auth::user();
            if (!$user || !$user->pharmacist) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $order = Order::where('id', $orderId)
                ->where('pharmacist_id', $user->pharmacist->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found or not authorized'
                ], 404);
            }

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
