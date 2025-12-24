<?php

namespace App\Http\Controllers\Api\pharmacist;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\Medication;
use App\Models\OrderMedication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PharmacistController extends Controller
{
    // List orders
    public function listPrescriptions(Request $request)
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
            'prescription.medications.medication',
            'prescription.prescriptionImage',
            'prescription.patient.user',
            'pharmacist',
            'patient.user'
        ])->where('pharmacist_id', $pharmacist->id)
        ->orderBy('created_at', 'desc');

        $orders = $ordersQuery->paginate($perPage, ['*'], 'page', $page);

        if ($orders->isEmpty()) {
            return response()->json([
                'status' => 'empty',
                'message' => 'No prescriptions found.',
                'data' => [],
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ], 200);
        }

        $data = $orders->getCollection()->map(function ($order) {
            $prescription = $order->prescription;
            
            if (!$prescription) {
                return null;
            }

            $patientName = $order->patient->user->full_name ?? $prescription->patient->user->full_name ?? null;

            if ($prescription->source === 'doctor_written') {
                $medicines = $prescription->medications->map(function ($item) {
                    return [
                        'name' => $item->medication->name ?? null,
                        'dosage' => $item->medication->dosage ?? null,
                        'boxes' => $item->boxes,
                        'instructions' => $item->instructions ?? null,
                    ];
                })->filter();
                
                $totalBoxes = $prescription->medications->sum('boxes');

                return [
                    'id' => $prescription->id,
                    'order_id' => $order->id,
                    'source' => 'doctor',
                    'patient' => $patientName,
                    'medicines' => $medicines,
                    'total_boxes' => $totalBoxes,
                    'status' => $order->status,
                    'created_at' => $prescription->created_at->toIso8601String(),
                ];
            } else { // patient_upload
                $prescriptionImage = $prescription->prescriptionImage;
                $imageUrl = $prescriptionImage && $prescriptionImage->file_path
                    ? asset('storage/' . ltrim($prescriptionImage->file_path, '/'))
                    : null;

                return [
                    'id' => $prescription->id,
                    'order_id' => $order->id,
                    'source' => 'patient_upload',
                    'patient' => $patientName,
                    'image_url' => $imageUrl,
                    'status' => $order->status,
                    'created_at' => $prescription->created_at->toIso8601String(),
                ];
            }
        })->filter();

        return response()->json([
            'status' => 'success',
            'data' => $data->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ], 200);
    }

    // View prescription
    public function viewPrescription(Request $request, $orderId)
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
            $pharmacist = Auth::user()->pharmacist;
            if (!$pharmacist) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pharmacist profile not found.'
                ], 404);
            }

            $order = Order::where('id', $orderId)
                ->where('pharmacist_id', $pharmacist->id)
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
                    'status' => $order->status,
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

    /**
     * Add prices to prescription medications
     * POST /api/pharmacist/prescriptions/{id}/add-price
     */
    public function addPrice(Request $request, $prescriptionId)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.medicine_name' => 'required|string|max:255',
            'items.*.dosage' =>'required|string',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $pharmacist = Auth::user()->pharmacist;
            if (!$pharmacist) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pharmacist profile not found.'
                ], 404);
            }

            // Get prescription and verify pharmacist has access via order
            $prescription = Prescription::with(['medications.medication'])
                ->where('id', $prescriptionId)
                ->first();

            if (!$prescription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription not found.'
                ], 404);
            }

            // Verify pharmacist has access to this prescription via an order
            $order = Order::where('prescription_id', $prescription->id)
                ->where('pharmacist_id', $pharmacist->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription not authorized for this pharmacist.'
                ], 403);
            }

            // Check if prescription is already priced
            if ($prescription->status === 'accepted') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription is already priced.'
                ], 422);
            }

            // Clear existing order medications
            OrderMedication::where('order_id', $order->id)->delete();

            $totalPrice = 0;
            $updatedItems = [];

            // Create order medications with prices
            foreach ($validated['items'] as $item) {
                $medicineName = $item['medicine_name'];
                $dosage = $item['dosage'];
                $price = (float) $item['price'];
                $quantity = 1; // Default quantity

                // Find medication or create new one
                $medication = Medication::firstOrCreate([
                    'name' => $medicineName,
                    'dosage' => $dosage,
                ]);

                // Calculate total price for this medication (price * quantity)
                $itemTotalPrice = $price * $quantity;

                // Create order medication
                $orderMedication = OrderMedication::create([
                    'order_id' => $order->id,
                    'medication_id' => $medication->id,
                    'total_quantity' => $quantity,
                    'total_price' => $itemTotalPrice,
                ]);

                $totalPrice += $itemTotalPrice;
                $updatedItems[] = [
                    'id' => $orderMedication->id,
                    'medicine_name' => $medicineName,
                    'dosage' => $dosage,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total_price' => $itemTotalPrice,
                ];
            }

            // Calculate total price from order medications
            $calculatedTotalPrice = OrderMedication::where('order_id', $order->id)
                ->sum('total_price');

            // Update prescription with total price and status
            $prescription->update([
                'total_price' => $calculatedTotalPrice,
                'status' => 'accepted',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Prices added successfully',
                'data' => [
                    'prescription_id' => $prescription->id,
                    'order_id' => $order->id,
                    'items' => $updatedItems,
                    'total_price' => $calculatedTotalPrice,
                    'status' => $prescription->status,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add prices.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Complete/deliver prescription
    public function complete(Request $request, $orderId)
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
