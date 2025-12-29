<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\DeliveryTask;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DeliveryController extends Controller
{
    /**
     * الطلبات الجاهزة للتوصيل وغير محجوزة
     */
    public function newOrders(Request $request)
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = $request->get('per_page', 10);

        $orders = Order::with(['pharmacist', 'patient'])
            ->where('status', 'ready_for_delivery')
            ->whereDoesntHave('deliveryTask')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $data = $orders->getCollection()->map(function ($order) {
            return [
                'order_id' => $order->id,
                'pharmacy' => [
                    'id' => $order->pharmacist->id ?? null,
                    'name' => $order->pharmacist->pharmacy_name ?? null,
                    'address' => $order->pharmacist->address ?? null,
                ],
                'patient_address' => $order->patient->address ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * قبول طلب توصيل
     */
    public function accept($order_id)
    {
        $delivery = Auth::user()->delivery;

        if (!$delivery) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = Order::where('id', $order_id)
            ->where('status', 'ready_for_delivery')
            ->whereDoesntHave('deliveryTask')
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not available'], 404);
        }

        $task = DeliveryTask::create([
            'order_id'   => $order->id,
            'delivery_id'=> $delivery->id,
            'status'     => 'picked_up_the_order',
            'assigned_at'=> now(),
        ]);

        $order->update([
            'status' => 'out_for_delivery'
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'task_id' => $task->id,
                'order_id' => $order->id,
                'status' => 'picked up the order',
            ],
        ]);
    }

    /**
     * مهام التوصيل الخاصة بالمندوب
     */
    public function tasks(Request $request)
    {
        $delivery = Auth::user()->delivery;

        if (!$delivery) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = $request->get('per_page', 10);

        $tasks = DeliveryTask::with([
            'order.pharmacist.user',
            'order.patient.user'
        ])
        ->where('delivery_id', $delivery->id)
        ->orderByDesc('created_at')
        ->paginate($perPage);

        $data = $tasks->getCollection()->map(function ($task) {
            return [
                'task_id' => $task->id,
                'status' => $task->status,
                'pharmacy_name' => $task->order->pharmacist->pharmacy_name ?? null,
                'pharmacy_phone' => $task->order->pharmacist->user->phone ?? null,
                'patient_name' => $task->order->patient->user->full_name ?? null,
                'patient_phone' => $task->order->patient->user->phone ?? null,
                'patient_address' => $task->order->patient->address ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    /**
     * تحديث حالة مهمة التوصيل
     */
    public function updateTaskStatus(Request $request, $task_id)
    {
        $request->validate([
            'status' => 'required|in:picked_up_the_order,on_the_way,delivered',
        ]);

        $delivery = Auth::user()->delivery;

        if (!$delivery) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task = DeliveryTask::where('id', $task_id)
            ->where('delivery_id', $delivery->id)
            ->first();

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $allowed = [
            'picked_up_the_order' => ['on_the_way'],
            'on_the_way' => ['delivered'],
        ];

        if (!in_array($request->status, $allowed[$task->status] ?? [])) {
            return response()->json(['message' => 'Invalid status transition'], 400);
        }

        $task->status = $request->status;

        if ($request->status === 'on_the_way') {
            $task->picked_at = now();
        }

        if ($request->status === 'delivered') {
            $task->delivered_at = now();
            $task->order->update(['status' => 'delivered']);
        }

        $task->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Task updated',
        ]);
    }
}
