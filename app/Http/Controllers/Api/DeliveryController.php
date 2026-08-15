<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\DeliveryTask;
use App\Models\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\DeliveryAssignmentService;
use App\Services\OSRMService;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryAssignmentService $deliveryAssignmentService,
        protected OSRMService $osrmService
    ) {}

    /**
     * The delivery driver's currently pending task offer, if any.
     * GET /api/delivery/offers/current
     */
    public function currentOffer(Request $request)
    {
        $delivery = Auth::user()->delivery;

        if (!$delivery) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $candidate = \App\Models\DeliveryTaskCandidate::where('delivery_id', $delivery->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (!$candidate) {
            return response()->json([
                'status' => 'success',
                'data' => null,
            ]);
        }

        $task = $candidate->task()->with('order.pharmacist', 'order.patient')->first();

        // The candidate may have gone stale since it was created — expire and
        // reassign before answering, so a driver never sees an offer that's
        // already moved on to someone else.
        $this->deliveryAssignmentService->expireStaleCandidateAndReassign($task);
        $candidate->refresh();

        if ($candidate->status !== 'pending') {
            return response()->json([
                'status' => 'success',
                'data' => null,
            ]);
        }

        $pharmacy = $task->order->pharmacist;
        $route = ($pharmacy && $pharmacy->latitude && $pharmacy->longitude)
            ? $this->osrmService->getRoute(
                (float) $delivery->current_latitude,
                (float) $delivery->current_longitude,
                (float) $pharmacy->latitude,
                (float) $pharmacy->longitude
            )
            : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'task_id' => $task->id,
                'order_id' => $task->order->id,
                'pharmacy' => [
                    'name' => $pharmacy->pharmacy_name ?? null,
                    'address' => $pharmacy->address ?? null,
                ],
                'patient_address' => $task->order->patient->address ?? null,
                'eta_minutes' => $route['duration_minutes'] ?? null,
                'distance_km' => $route['distance_km'] ?? null,
                'sent_at' => $candidate->sent_at?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Accept the currently offered task.
     * POST /api/delivery/tasks/{task_id}/accept-offer
     */
    public function acceptOffer($task_id)
    {
        $delivery = Auth::user()->delivery;

        if (!$delivery) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task = DeliveryTask::find($task_id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $this->deliveryAssignmentService->expireStaleCandidateAndReassign($task);

        $result = $this->deliveryAssignmentService->acceptTask($task, $delivery);

        if (!$result) {
            return response()->json(['message' => 'This offer is no longer available'], 409);
        }

        if (isset($result['conflict'])) {
            return response()->json(['message' => 'This task was already assigned to another driver'], 409);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'task_id' => $result['task']->id,
                'status' => $result['task']->status,
                'route' => $result['route'],
            ],
        ]);
    }

    /**
     * Reject the currently offered task; the system immediately offers it to
     * the next-nearest available driver.
     * POST /api/delivery/tasks/{task_id}/reject
     */
    public function reject($task_id)
    {
        $delivery = Auth::user()->delivery;

        if (!$delivery) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task = DeliveryTask::find($task_id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $rejected = $this->deliveryAssignmentService->rejectCandidate($task, $delivery);

        if (!$rejected) {
            return response()->json(['message' => 'No pending offer found for this task'], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Offer rejected',
        ]);
    }

    /**
     * الطلبات الجاهزة للتوصيل وغير محجوزة
     * GET /api/
     */
    public function newOrders(Request $request)
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = $request->get('per_page', 10);

        $orders = Order::with(['pharmacist', 'patient'])
            ->where('status', 'ready_for_delivery')
            ->whereDoesntHave('deliveryTask')// Check if the order doesn't have a delivery task
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
                'per_page' => $orders->perPage(),
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
            'status'     => 'picking_up_the_order',
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
                'status' => $task->status,
            ],
        ]);
    }
    /**
     * Summary of setDeliveryFee
     * @param Request $request
     * @param mixed $task_id
     * @return \Illuminate\Http\JsonResponse
     * 
     */
    public function setDeliveryFee(Request $request, $task_id)
{
    $request->validate([
        'delivery_fee' => 'required|numeric|min:0',
    ]);

    $delivery = Auth::user()->delivery;
    if (!$delivery) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $task = DeliveryTask::with('order')
        ->where('id', $task_id)
        ->where('delivery_id', $delivery->id)
        ->whereIn('status', ['picking_up_the_order', 'on_the_way'])
        ->first();

    if (!$task) {
        return response()->json(['message' => 'Task not found'], 404);
    }

    if ($task->delivery_fee !== null) {
        return response()->json(['message' => 'Delivery fee already set'], 400);
    }

    $task->update([
        'delivery_fee' => $request->delivery_fee,
    ]);

    return response()->json([
        'status' => 'success',
        'data' => [
            'task_id' => $task->id,
            'delivery_fee' => $task->delivery_fee,
            'total_amount' =>
                $task->order->total_amount + $task->delivery_fee,
        ],
    ]);
}


    /**
     * مهام التوصيل الخاصة بالمندوب
     */
    public function tasks(Request $request)
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|in:pending,picking_up_the_order,on_the_way,delivered',
        ]);

        $delivery = Auth::user()->delivery;

        if (!$delivery) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = $request->get('per_page', 10);

        $tasksQuery = DeliveryTask::with([
            'order.pharmacist.user',
            'order.patient.user'
        ])
        ->where('delivery_id', $delivery->id);

        if ($request->filled('status')) {
            $tasksQuery->where('status', $request->status);
        }

        $tasks = $tasksQuery->orderByDesc('assigned_at')->paginate($perPage);

        $data = $tasks->getCollection()->map(function ($task) {
            return [
                'task_id' => $task->id,
                'order_id' => $task->order->id,
                'status' => $task->status,
                'pharmacy_name' => $task->order->pharmacist->pharmacy_name ?? null,
                'pharmacy_phone' => $task->order->pharmacist->user->phone ?? null,
                'pharmacy_address' => $task->order->pharmacist->address ?? null,
                'patient_name' => $task->order->patient->user->full_name ?? null,
                'patient_phone' => $task->order->patient->user->phone ?? null,
                'patient_address' => $task->order->patient->address ?? null,
                'order_price' => $task->order->total_amount,
                'delivery_fee' => $task->delivery_fee,
                'total_amount' => $task->order->total_amount + ($task->delivery_fee ?? 0),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'per_page' => $tasks->perPage(),
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
            'status' => 'required|in:pending,picking_up_the_order,on_the_way,delivered',
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
            'pending' => ['picking_up_the_order'],
            'picking_up_the_order' => ['on_the_way'],
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

            $totalAmount =
            $task->order->total_amount + ($task->delivery_fee ?? 0);
            $task->order->update([
            'status' => 'delivered',
            'total_amount' => $totalAmount,
    ]);
        }


        $task->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Task status updated successfully',
            'updated_at' => $task->updated_at ? $task->updated_at->toDateTimeString() : null,
      
        ]);
    }

    /**
     * Get delivery agent profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $delivery = $user->delivery;

        if (!$delivery) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.delivery_profile_not_found')
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.profile_retrieved'),
            'data' => [
                'id' => $delivery->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $delivery->gender,
                'gender_label' => \App\Support\Locale::label('gender', $delivery->gender),
                'vehicle_type' => $delivery->vehicle_type,
                'plate_number' => $delivery->plate_number,
                'driving_license_id' => $delivery->driving_license_id,
                'driving_license_file' => $delivery->driving_license_id ? asset('/storage/' . $delivery->drivingLicense->file_path) : null,
                'image_id' => $delivery->delivery_image_id,
                'image' => $delivery->delivery_image_id ? asset('/storage/' . $delivery->deliveryImage->file_path) : null,
                'rating_avg' => $delivery->rating_avg,
                'role' => $user->role,
                'email_verified' => $user->email_verified_at ? true : false,
            ]
        ]);
    }

    /**
     * Update delivery agent profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'gender' => 'sometimes|in:male,female',
            'vehicle_type' => 'sometimes|string|max:255',
            'plate_number' => 'sometimes|string|max:255',
            // The client sends the actual file directly in this request.
            'image' => 'sometimes|file|mimes:jpeg,png,jpg,gif|max:2048',
            'driving_license' => 'sometimes|file|mimes:pdf,jpeg,png,jpg,gif|max:5120',
        ]);

        $user = $request->user();
        $delivery = $user->delivery;

        if (!$delivery) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.delivery_profile_not_found')
            ], 404);
        }
        if ($request->has('full_name')) {
            $user->full_name = $request->full_name;
            $user->save();
        }
        if ($request->has('email')) {
            $user->email = $request->email;
            $user->save();
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
            $user->save();
        }
        if ($request->has('gender')) {
            $delivery->gender = $request->gender;
        }
        if ($request->has('vehicle_type')) {
            $delivery->vehicle_type = $request->vehicle_type;
        }
        if ($request->has('plate_number')) {
            $delivery->plate_number = $request->plate_number;
        }

        $oldImageId = $delivery->delivery_image_id;
        $oldLicenseId = $delivery->driving_license_id;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->store('profile_images', 'public');
            $upload = Upload::create([
                'user_id' => $user->id,
                'category' => 'profile',
                'file' => basename($path),
                'file_path' => $path,
                'mime' => $file->getClientMimeType(),
            ]);
            $delivery->delivery_image_id = $upload->id;
        }
        if ($request->hasFile('driving_license')) {
            $file = $request->file('driving_license');
            $path = $file->store('certificates', 'public');
            $upload = Upload::create([
                'user_id' => $user->id,
                'category' => 'certificate',
                'file' => basename($path),
                'file_path' => $path,
                'mime' => $file->getClientMimeType(),
            ]);
            $delivery->driving_license_id = $upload->id;
        }

        $delivery->save();

        if ($oldImageId && $oldImageId != $delivery->delivery_image_id) {
            if ($old = Upload::find($oldImageId)) {
                Storage::disk('public')->delete($old->file_path);
                $old->delete();
            }
        }
        if ($oldLicenseId && $oldLicenseId != $delivery->driving_license_id) {
            if ($old = Upload::find($oldLicenseId)) {
                Storage::disk('public')->delete($old->file_path);
                $old->delete();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.profile_updated'),
            'data' => [
                'delivery' => [
                    'id' => $delivery->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'gender' => $delivery->gender,
                    'vehicle_type' => $delivery->vehicle_type,
                    'plate_number' => $delivery->plate_number,
                    'image_id' => $delivery->delivery_image_id,
                    'image' => $delivery->delivery_image_id ? asset('/storage/' . $delivery->deliveryImage->file_path) : null,
                    'driving_license_id' => $delivery->driving_license_id,
                    'driving_license_file' => $delivery->driving_license_id ? asset('/storage/' . $delivery->drivingLicense->file_path) : null,
                ]
            ]
        ]);
    }
}
