<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Order;

class PrescriptionController extends Controller
{
    // List orders
    public function index(Request $request)
    {
        $pharmacist = Auth::user()->pharmacist;
        if (!$pharmacist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pharmacist profile not found.'
            ], 404);
        }

        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $ordersQuery = Order::with([
                'prescription.items.medication',
                'prescription.patient.user'
            ])
            ->where('pharmacist_id', $pharmacist->id)
            ->where('status', 'sent_to_pharmacy')
            ->orderBy('created_at', 'desc');

        $orders = $ordersQuery->paginate($perPage, ['*'], 'page', $page);

        $data = $orders->map(function ($order) {
            $prescription = $order->prescription;
            $patientName = $prescription->patient->user->full_name ?? null;

            if ($prescription->source === 'doctor_written') {
                $medicines = $prescription->items->map(function ($item) {
                    return [
                        'name' => $item->medication->name,
                        'dosage' => $item->medication->dosage,
                        'boxes' => $item->boxes,
                    ];
                });
                $totalBoxes = $prescription->items->sum('boxes');

                return [
                    'id' => $prescription->id,
                    'source' => 'doctor',
                    'patient' => $patientName,
                    'medicines' => $medicines,
                    'total_boxes' => $totalBoxes,
                    'status' => $order->status,
                    'created_at' => $prescription->created_at->toIso8601String(),
                ];
            } else { // patient_upload
                $imageUrl = $prescription->prescription_image_id
                    ? url("/uploads/prescriptions/{$prescription->prescription_image_id}.jpg")
                    : null;

                return [
                    'id' => $prescription->id,
                    'source' => 'patient_upload',
                    'patient' => $patientName,
                    'image_url' => $imageUrl,
                    'status' => $order->status,
                    'created_at' => $prescription->created_at->toIso8601String(),
                ];
            }
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

    // View prescription
    public function show(Request $request, $orderId)
    {
        $status = $request->query('status'); // ?status=pending

        $query = Order::with('prescription.medications.medication', 'patient.user')
            ->where('id', $orderId);

        if ($status) {
            $query->where('status', $status);
        }

        $order = $query->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }

        $medicines = $order->prescription ? $order->prescription->medications->map(function ($item) {
            return [
                'name' => $item->medication->name,
                'dosage' => $item->medication->dosage,
                'instructions' => $item->instructions,
            ];
        }) : null;

        $responseData = [
            'id' => $order->id,
            'patient' => $order->patient->user->full_name,
            'status' => $order->status,
        ];

        if ($medicines) {
            $responseData['medicines'] = $medicines;
        }

        if ($order->prescription && $order->prescription->notes) {
            $responseData['notes'] = $order->prescription->notes;
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseData
        ]);
    }

    // Accept prescription
    public function accept($orderId)
    {
        try {
            $order = Order::where('id', $orderId)
                ->where('pharmacist_id', Auth::id())
                ->first();

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found or not authorized.'
                ], 404);
            }

            $order->update(['status' => 'accepted']);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status
                ],
                'message' => 'Order accepted'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to accept order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Complete/deliver prescription
    public function deliver(Request $request, $orderId)
    {
        $validated = $request->validate([
            'delivered' => 'required|boolean',
            'delivery_method' => 'required|string|in:pickup,delivery',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::findOrFail($orderId);

            if (!$validated['delivered']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Delivery not confirmed.'
                ], 422);
            }

            $order->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'delivery_method' => $validated['delivery_method'],
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order marked delivered',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'delivered_at' => $order->delivered_at->toIso8601String(),
                    'delivery_method' => $order->delivery_method,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark order delivered',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Reject prescription
    public function reject(Request $request, $order_id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $order = Order::findOrFail($order_id);

        $order->status = 'rejected';
        $order->rejection_reason = $request->reason;
        $order->save();

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
            ],
            'message' => 'Order rejected'
        ]);
    }
}
