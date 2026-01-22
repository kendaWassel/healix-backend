<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Medication;
use App\Models\Pharmacist;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\PrescriptionMedication;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PharmacistController extends Controller
{
    use AuthorizesRequests;
    /**
     * GET api/pharmacist/prescriptions
     */
    public function listPrescriptions(Request $request)
    {
        $this->authorize('viewAny', Prescription::class);
        $pharmacist = Auth::user()->pharmacist;
        if (!$pharmacist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pharmacist profile not found.'
            ], 404);
        }

        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $prescriptionsQuery = Prescription::with([
            'patient',
            'patient.user',
            'prescriptionImage',
            'medications.medication',
            'order' => function ($query) {
                $query->orderByDesc('created_at')->limit(1);
            },
        ])->where('status', 'sent_to_pharmacy');

        // Filter by pharmacist if needed - prescriptions assigned to this pharmacist
        $prescriptionsQuery->where('pharmacist_id', $pharmacist->id);

        $prescriptions = $prescriptionsQuery->paginate($perPage, ['*'], 'page', $page);

        if ($prescriptions->isEmpty()) {
            return response()->json([
                'status' => 'empty',
                'message' => 'No prescriptions found.',
                'data' => [],
                'meta' => [
                    'current_page' => $prescriptions->currentPage(),
                    'last_page' => $prescriptions->lastPage(),
                    'per_page' => $prescriptions->perPage(),
                    'total' => $prescriptions->total(),
                ],
            ], 200);
        }

        $data = $prescriptions->getCollection()->map(function ($prescription) {
            $patientName = $prescription->patient && $prescription->patient->user 
                ? $prescription->patient->user->full_name 
                : null;

            $medicines = $prescription->medications->map(function ($item) {
                return [
                    'name' => $item->medication->name ?? null,
                    'dosage' => $item->medication->dosage ?? null,
                    'boxes' => $item->boxes,
                    'instructions' => $item->instructions ?? null,
                ];
            })->filter();

            $totalBoxes = $prescription->medications->sum('boxes');
            
            // Get the latest order (since order() is hasMany)
            $order = $prescription->order->first();

            if ($prescription->source === 'doctor_written') {
                return [
                    'prescription_id' => $prescription->id,
                    'order_id' => $order ? $order->id : null,
                    'source' => 'doctor',
                    'patient' => $patientName,
                    'medicines' => $medicines,
                    'total_quantity' => $prescription->total_quantity ?? $totalBoxes,
                    'status' => $prescription->status, // Use prescription status instead of order status
                    // 'created_at' => $prescription->created_at->toIso8601String(),
                ];
            } else { // patient_upload
                $prescriptionImage = $prescription->prescriptionImage;
                $imageUrl = $prescriptionImage && $prescriptionImage->file_path
                    ? asset('storage/' . ltrim($prescriptionImage->file_path, '/'))
                    : null;

                $result = [
                    'prescription_id' => $prescription->id,
                    'order_id' => $order ? $order->id : null,
                    'source' => 'patient_upload',
                    'patient' => $patientName,
                    'image_url' => $imageUrl,
                    'status' => $prescription->status, // Use prescription status instead of order status
                    'created_at' => $prescription->created_at->toIso8601String(),
                ];

                if ($medicines->isNotEmpty()) {
                    $result['medicines'] = $medicines;
                    $result['total_quantity'] = $prescription->total_quantity ?? $totalBoxes;
                }

                return $result;
            }
        })->filter();

        return response()->json([
            'status' => 'success',
            'data' => $data->values(),
            'meta' => [
                'current_page' => $prescriptions->currentPage(),
                'last_page' => $prescriptions->lastPage(),
                'per_page' => $prescriptions->perPage(),
                'total' => $prescriptions->total(),
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
    public function accept($prescriptionId)
    {
        try {
            DB::beginTransaction();

            $pharmacist = Auth::user()->pharmacist;
            if (!$pharmacist) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pharmacist profile not found.'
                ], 404);
            }

            // Find prescription
            $prescription = Prescription::with('order')
                ->where('id', $prescriptionId)
                ->first();

            if (!$prescription) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription not found.'
                ], 404);
            }

            // Validate prescription is assigned to this pharmacist
            if ($prescription->pharmacist_id !== $pharmacist->id) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to accept this prescription.'
                ], 403);
            }

            // Validate prescription can be accepted (should be in sent_to_pharmacy or pending status)
            if (!in_array($prescription->status, ['sent_to_pharmacy', 'pending', 'created'])) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription cannot be accepted. Current status: ' . $prescription->status
                ], 422);
            }

            // Update prescription status to accepted
            $prescription->update([
                'status' => 'accepted',
                'pharmacist_id' => $pharmacist->id,
            ]);

            // Find or create order and update status
            $order = $prescription->order->first();
            if ($order) {
                $order->update([
                    'status' => 'accepted',
                    'pharmacist_id' => $pharmacist->id,
                ]);
            } else {
                // Create order if it doesn't exist
                $order = Order::create([
                    'prescription_id' => $prescription->id,
                    'patient_id' => $prescription->patient_id,
                    'pharmacist_id' => $pharmacist->id,
                    'status' => 'accepted',
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'prescription_id' => $prescription->id,
                    'order_id' => $order->id,
                    'prescription_status' => $prescription->status,
                    'order_status' => $order->status,
                ],
                'message' => 'Prescription accepted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to accept prescription.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add prices to prescription medications
     * POST /api/pharmacist/prescriptions/{id}/add-price
     */
    public function addPrice(Request $request, $orderId)
    {
        $this->authorize('addPrice', Prescription::class);
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.medicine_name' => 'required|string|max:255',
            'items.*.dosage' => 'required|string',
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


            // Verify pharmacist has access to this prescription via an order
            $order = Order::where('id', $orderId)
                ->where('pharmacist_id', $pharmacist->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription not authorized for this pharmacist.'
                ], 403);
            }
            $prescription = $order->prescription;

            // Check if prescription is already priced
            if ($prescription->status === 'priced') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription is already priced.'
                ], 422);
            }

            // Only allow pricing when prescription is accepted
            if ($prescription->status !== 'accepted') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription must be accepted before adding prices.'
                ], 422);
            }

            $totalPrice = 0;
        $updatedItems = [];
        $totalQuantity = 0;
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

            // Add to prescription medications
            PrescriptionMedication::create([
                'prescription_id' => $prescription->id,
                'medication_id' => $medication->id,
                'boxes' => $quantity,
                'price' => $price,
            ]);

            $totalPrice += $itemTotalPrice;
            $totalQuantity += $quantity;
            $updatedItems[] = [
                'medicine_name' => $medicineName,
                'dosage' => $dosage,
                'quantity' => $quantity,
                'price' => $price,
            ];
        }

        // Calculate total price from order medications
        $calculatedTotalPrice = PrescriptionMedication::where('prescription_id', $prescription->id)
            ->selectRaw('SUM(price * boxes) as total')
            ->value('total') ?? 0;

        // Update prescription with totals and change status to priced
        $prescription->update([
            'total_quantity' => $totalQuantity,
            'total_price' => $calculatedTotalPrice,
            'status' => 'priced',
        ]);

        // Refresh to get updated status
        $prescription->refresh();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Prices added successfully',
                'data' => [
                    'prescription_id' => $prescription->id,
                    'order_id' => $order->id,
                    'items' => $updatedItems,
                    'total_quantity' => $totalQuantity,
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

    /**
     * GET /api/pharmacist/my-orders
     * View all accepted orders for the pharmacist
     */
    public function myOrders(Request $request)
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
        $pharmacist = Auth::user()->pharmacist;
        if (!$pharmacist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pharmacist profile not found.'
            ], 404);
        }

        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $ordersQuery = Order::select('orders.*')
            ->join('prescriptions', 'orders.prescription_id', '=', 'prescriptions.id')
            ->join('pharmacists', 'orders.pharmacist_id', '=', 'pharmacists.id')
            ->where('pharmacists.id', $pharmacist->id)
            ->where('orders.status', 'accepted');

        $orders = $ordersQuery->paginate($perPage, ['*'], 'page', $page);

        if ($orders->isEmpty()) {
            return response()->json([
                'status' => 'success',
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
            $patientName = $prescription->patient->user->full_name ?? 'Unknown';

            $medicines = $prescription->medications->map(function ($item) {
                return [
                    'name' => $item->medication->name ?? 'Unknown',
                    'dosage' => $item->medication->dosage ?? 'Unknown',
                    'quantity' => $item->boxes ?? 0,
                    'price_per_unit' => $item->price ?? 0,
                ];
            });

            $totalQuantity = $prescription->total_quantity ?? $medicines->sum('quantity');
            $totalMedicinePrice = $prescription->total_price ?? $medicines->sum(function ($medicine) {
                return $medicine['quantity'] * $medicine['price_per_unit'];
            });

            
            $result = [
                'id' => $order->id,
                'source' => $prescription->source === 'patient_uploaded' ? 'paper' : 'electronic',
                'patient' => $patientName,
                'medicines' => $medicines,
                'total_quantity' => $totalQuantity,
                'total_medicine_price' => $totalMedicinePrice,
                'status' => $order->status,
            ];
            // Include prescription image URL if source is paper
            if ($prescription->source === 'patient_uploaded') {
                $prescriptionImage = $prescription->prescriptionImage;
                $imageUrl = $prescriptionImage && $prescriptionImage->file_path
                    ? asset('storage/' . ltrim($prescriptionImage->file_path, '/'))
                    : null;
                $result['image_url'] = $imageUrl;
            }

            return $result;
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
        ], 200);
    }

    /**
     * Pharmacist: Reject prescription
     * 
     * Endpoint: POST /api/pharmacist/prescriptions/{prescription_id}/reject
     */
    public function reject(Request $request, $prescriptionId)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $pharmacist = Auth::user()->pharmacist;
            if (!$pharmacist) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pharmacist profile not found.'
                ], 404);
            }

            // Find prescription
            $prescription = Prescription::with('order')
                ->where('id', $prescriptionId)
                ->first();

            if (!$prescription) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription not found.'
                ], 404);
            }

            // Validate prescription is assigned to this pharmacist
            if ($prescription->pharmacist_id !== $pharmacist->id) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to reject this prescription.'
                ], 403);
            }

            // Check if prescription can be rejected (not already delivered, rejected, or priced)
            if (in_array($prescription->status, ['delivered', 'rejected', 'priced', 'accepted'])) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prescription cannot be rejected. Current status: ' . $prescription->status
                ], 422);
            }

            // Update prescription status to rejected
            $prescription->update([
                'status' => 'rejected',
            ]);

            // Update order status if exists
            $order = $prescription->order->first();
            if ($order) {
                $order->update([
                    'status' => 'rejected',
                    'rejection_reason' => $validated['reason'],
                    'pharmacist_id' => $pharmacist->id,
                ]);
            } else {
                // Create order if it doesn't exist
                $order = Order::create([
                    'prescription_id' => $prescription->id,
                    'patient_id' => $prescription->patient_id,
                    'pharmacist_id' => $pharmacist->id,
                    'status' => 'rejected',
                    'rejection_reason' => $validated['reason'],
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'prescription_id' => $prescription->id,
                    'order_id' => $order->id,
                    'prescription_status' => $prescription->status,
                    'order_status' => $order->status,
                    'rejection_reason' => $order->rejection_reason,
                ],
                'message' => 'Prescription rejected successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject prescription.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pharmacist: Track Current Active Orders
     * 
     * Endpoint: GET /api/pharmacist/orders/track
     */
    public function trackOrders(Request $request)
    {
        $pharmacist = Auth::user()->pharmacist;
        if (!$pharmacist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pharmacist profile not found.'
            ], 404);
        }

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = $request->get('per_page', 10);

        // Get all orders
        $orders = Order::with([
            'deliveryTask.delivery','patient.user','prescription.medications.medication'
        ])
        ->where('pharmacist_id', $pharmacist->id)

        ->whereIn('status', ['accepted', 'ready_for_delivery','out_for_delivery'])
        ->orderByDesc('created_at')
        ->paginate($perPage)->appends($request->query());

        $data = $orders->getCollection()->map(function ($order) {
            $deliveryTask = $order->deliveryTask;
            $deliveryData = null;

            if ($deliveryTask && $deliveryTask->delivery_id) {
                $delivery = $deliveryTask->delivery;
                $deliveryUser = $delivery->user;
                
                // Get delivery image
                $deliveryImageUrl = null;
                if ($delivery->delivery_image_id) {
                    $upload = \App\Models\Upload::find($delivery->delivery_image_id);
                    if ($upload && $upload->file_path) {
                        $deliveryImageUrl = asset('storage/' . ltrim($upload->file_path, '/'));
                    }
                }

                $deliveryData = [
                    'task_id' => $deliveryTask->id,
                    'delivery_agent' => [
                        'name' => $deliveryUser ? $deliveryUser->full_name : null,
                        'driver_image' => $deliveryImageUrl,
                        'phone' => $deliveryUser ? $deliveryUser->phone : null,
                        'vehicle_type' => $delivery->vehicle_type,
                        'plate_number' => $delivery->plate_number,
                        'driver_status' => $deliveryTask->status,
                    ],
                    'assigned_at' => $deliveryTask->assigned_at ? $deliveryTask->assigned_at->format('Y-m-d H:i:s') : null,
                ];
            }

            // Map medications with name, quantity, price, and dosage
            $medications = $order->prescription && $order->prescription->medications 
                ? $order->prescription->medications->map(function ($item) {
                    return [
                        'name' => $item->medication->name ?? null,
                        'quantity' => (int) ($item->boxes ?? 0),
                        'price' => (float) ($item->price ?? 0),
                        'dosage' => $item->medication->dosage ?? null,
                    ];
                })->filter(function ($med) {
                    return $med['name'] !== null;
                })->values()
                : [];

            $result = [
                'order_id' => $order->id,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'medications' => $medications,
            ];

            if ($deliveryData) {
                $result['delivery'] = $deliveryData;
            } else {
                $result['delivery'] = null;
                $result['message'] = 'No delivery agent has accepted this order yet';
            }

            return $result;
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total()
            ],
        ], 200);
    }

    /**
     * Pharmacist: Track a specific active order
     * See details and delivery status
     * Endpoint: GET /api/pharmacist/orders/{orderId}/track
     */
    public function trackOrder(Request $request, $orderId)
    {
        $pharmacist = Auth::user()->pharmacist;
        if (!$pharmacist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pharmacist profile not found.'
            ], 404);
        }

        $order = Order::with([
            'deliveryTask.delivery.user',
            'patient.user',
            'prescription.medications.medication'
        ])
        ->where('id', $orderId)
        ->where('pharmacist_id', $pharmacist->id)
        ->whereNotIn('status', ['delivered', 'rejected'])
        ->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found or not accessible.'
            ], 404);
        }

        $deliveryTask = $order->deliveryTask;
        $deliveryData = null;

        if ($deliveryTask && $deliveryTask->delivery_id) {
            $delivery = $deliveryTask->delivery;
            $deliveryUser = $delivery->user;
            
            // Get delivery image
            $deliveryImageUrl = null;
            if ($delivery->delivery_image_id) {
                $upload = \App\Models\Upload::find($delivery->delivery_image_id);
                if ($upload && $upload->file_path) {
                    $deliveryImageUrl = asset('storage/' . ltrim($upload->file_path, '/'));
                }
            }

            $deliveryData = [
                'task_id' => $deliveryTask->id,
                'status' => $deliveryTask->status,
                'delivery_agent' => [
                    'name' => $deliveryUser ? $deliveryUser->full_name : null,
                    'driver_image' => $deliveryImageUrl,
                    'phone' => $deliveryUser ? $deliveryUser->phone : null,
                    'vehicle_type' => $delivery->vehicle_type,
                    'plate_number' => $delivery->plate_number,
                ],
                'assigned_at' => $deliveryTask->assigned_at ? $deliveryTask->assigned_at->format('Y-m-d H:i:s') : null,
                'picked_at' => $deliveryTask->picked_at ? $deliveryTask->picked_at->format('Y-m-d H:i:s') : null,
                'delivered_at' => $deliveryTask->delivered_at ? $deliveryTask->delivered_at->format('Y-m-d H:i:s') : null,
            ];
        }

        $result = [
            'order_id' => $order->id,
            'order_status' => $order->status,
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
        ];

        if ($deliveryData) {
            $result['delivery'] = $deliveryData;
        } else {
            $result['delivery'] = null;
            $result['message'] = 'No delivery agent has accepted this order yet';
        }

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ], 200);
    }

    /**
     * Pharmacist: View Previous Delivered Orders (History)
     * 
     * Endpoint: GET /api/pharmacist/orders/history
     */
    public function ordersHistory(Request $request)
    {
        $pharmacist = Auth::user()->pharmacist;
        if (!$pharmacist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pharmacist profile not found.'
            ], 404);
        }

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = $request->get('per_page', 10);

        // Get delivered orders
        $orders = Order::with([
            'patient.user',
            'prescription.medications.medication',
            'deliveryTask.delivery.user',
        ])
        ->where('pharmacist_id', $pharmacist->id)
        ->where('status', 'delivered')
        ->orderByDesc('created_at')
        ->paginate($perPage)->appends($request->query());

        $data = $orders->getCollection()->map(function ($order) {
            $patient = $order->patient;
            $patientUser = $patient->user;
            
            // Get delivery info
            $deliveryTask = $order->deliveryTask;
            $deliveryData = null;
            if ($deliveryTask && $deliveryTask->delivery_id) {
                $delivery = $deliveryTask->delivery;
                $deliveryUser = $delivery->user;
                $deliveryData = [
                    'name' => $deliveryUser ? $deliveryUser->full_name : null,
                    'phone' => $deliveryUser ? $deliveryUser->phone : null,
                ];
            }

            // Get medications with prices
            $medications = $order->prescription->medications->map(function ($item) {
                return [
                    'medication_name' => $item->medication->name ?? null,
                    'quantity' => (int) ($item->boxes ?? 0),
                    'price' => (float) ($item->price ?? 0),
                ];
            })->filter(function ($med) {
                return $med['medication_name'] !== null;
            })->values();

            $totalMedicinePrice = $prescription->total_price ?? $medications->sum(function ($med) {
                return $med['quantity'] * $med['price'];
            });

            return [
                'order_id' => $order->id,
                'delivered_at' => $deliveryTask && $deliveryTask->delivered_at 
                    ? $deliveryTask->delivered_at->format('Y-m-d H:i:s')
                    : $order->updated_at->format('Y-m-d H:i:s'),
                'patient' => [
                    'name' => $patientUser ? $patientUser->full_name : null,
                    'address' => $patient->address ?? null,
                    'phone' => $patientUser ? $patientUser->phone : null,
                ],
                'delivery' => $deliveryData,
                'medications' => $medications,
                'total_medicine_price' => $totalMedicinePrice,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total()
            ],
        ], 200);
    }

    /**
     * Get pharmacist profile
     */
    public function getProfile(Request $request)
    {
        $this->authorize('view', Pharmacist::class);
        $user = $request->user();
        $pharmacist = $user->pharmacist;

        if (!$pharmacist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pharmacist profile not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile retrieved successfully',
            'data' => [
                'id' => $pharmacist->id,
                'full_name' => $user->full_name,
                'pharmacy_name' => $pharmacist->pharmacy_name,
                'address' => $pharmacist->address,
                'working_hours' => [
                    'from' => $pharmacist->from,
                    'to' => $pharmacist->to,
                ],
                'rating_avg' => $pharmacist->rating_avg,
            ]
        ]);
    }

    /**
     * Update pharmacist profile
     */
    public function updateProfile(Request $request)
    {
        $this->authorize('update', Pharmacist::class);
        $request->validate([
            'from' => 'sometimes|date_format:H:i',
            'to' => 'sometimes|date_format:H:i',
            'address' => 'sometimes|string|max:500',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
        ]);

        $user = $request->user();
        $pharmacist = $user->pharmacist;

        if (!$pharmacist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pharmacist profile not found'
            ], 404);
        }

        if ($request->has('from')) {
            $pharmacist->from = $request->from;
        }
        if ($request->has('to')) {
            $pharmacist->to = $request->to;
        }
        if ($request->has('address')) {
            $pharmacist->address = $request->address;
        }
        if ($request->has('latitude')) {
            $pharmacist->latitude = $request->latitude;
        }
        if ($request->has('longitude')) {
            $pharmacist->longitude = $request->longitude;
        }
        $pharmacist->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => [
                'pharmacist' => [
                    'id' => $pharmacist->id,
                    'address' => $pharmacist->address,
                    'working_hours' => [
                        'from' => $pharmacist->from,
                        'to' => $pharmacist->to,
                    ],
                ]
            ]
        ]);
    }
}
