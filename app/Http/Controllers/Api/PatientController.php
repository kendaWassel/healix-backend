<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Upload;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Consultation;
use App\Models\Prescription;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    public function getPatientScheduledConsultations(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
        //get patient ID from authenticated user
        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            return response()->json([
                'status' => 'empty',
                'message' => 'Patient not found for this user',
                'data' => []
            ], 404);
        }

        $patientId = $patient->id;


        try {
            $perPage = $request->get('per_page', 10);
            $consultations = Consultation::where('patient_id', $patientId)->paginate($perPage)->appends($request->query());
            if ($consultations->isEmpty()) {
                return response()->json([
                    'status' => 'empty',
                    'message' => 'No scheduled consultations found',
                    'data' => []
                ], 200);
            }
            $data = $consultations->getCollection()->map(function ($consultation) {
                $doctor = $consultation->doctor;
                $doctorImage = null;
                if ($doctor && !empty($doctor->doctor_image_id)) {
                    $upload = Upload::find($doctor->doctor_image_id);
                    if ($upload && $upload->file_path) {
                        $doctorImage = asset('storage/public/' . ltrim($upload->file_path, '/'));
                    }
                }

                return [
                    'id' => $consultation->id,
                    'doctor_id' => $doctor ? $doctor->id : null,
                    'doctor_name' => $doctor ? 'Dr. ' . $doctor->user?->full_name : null,
                    'doctor_phone' => $doctor->user->phone,
                    'doctor_image' => $doctorImage,
                    'type' => $consultation->type,
                    'scheduled_at' => $consultation->scheduled_at ? $consultation->scheduled_at->toIso8601String() : null,
                    'specialization' => $doctor && $doctor->specialization ? $doctor->specialization->name : null,
                    'fee' => $doctor ? $doctor->consultation_fee : null,
                    'status' => $consultation->status,
                    
                ];
            });
            $meta = [
                'current_page' => $consultations->currentPage(),
                'last_page' => $consultations->lastPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => $meta
            ], 200);


            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve scheduled consultations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Patient: Get all prescriptions belonging to the logged-in patient.
     * Includes both doctor-written and patient-uploaded (image) prescriptions.
     *
     * Endpoint: GET /api/patient/prescriptions
     */
    public function getPatientPrescriptions(Request $request)
    {
        $validated = $request->validate([
            'page'     => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Patient not found for this user.',
                'data'    => [],
            ], 404);
        }

        $perPage = $request->get('per_page', 10);

        // Load both doctor and upload relationships to handle both types of prescriptions
        $query = Prescription::with(['doctor.user', 'prescriptionImage'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('created_at');

        $prescriptions = $query->paginate($perPage)->appends($request->query());

        if ($prescriptions->isEmpty()) {
            return response()->json([
                'status'  => 'empty',
                'message' => 'No prescriptions found for this patient.',
                'data'    => [
                    'items' => [],
                    'meta'  => [
                        'page'     => $prescriptions->currentPage(),
                        'per_page' => $prescriptions->perPage(),
                        'total'    => $prescriptions->total(),
                    ],
                ],
            ], 200);
        }

        $items = $prescriptions->getCollection()->map(function (Prescription $prescription) {
            $doctorUser = $prescription->doctor?->user;
            $prescriptionImage = $prescription->prescriptionImage;

            // Determine doctor name - for patient-uploaded, it might be null
            $doctorName = null;
            if ($prescription->source === 'doctor_written' && $doctorUser) {
                $doctorName = 'Dr. ' . $doctorUser->full_name;
            } elseif ($prescription->source === 'patient_uploaded') {
                // For patient-uploaded prescriptions, doctor_name can be null
                $doctorName = $doctorUser ? 'Dr. ' . $doctorUser->full_name : null;
            }

            return [
                'id'          => $prescription->id,
                'doctor_name' => $doctorName,
                'diagnosis'   => $prescription->diagnosis,
                'status'      => $prescription->status,
                'issued_at'   => $prescription->created_at?->toIso8601String(),
                'source'      => $prescription->source, // 'doctor_written' or 'patient_uploaded'
                'image_url'   => $prescriptionImage ? asset('storage/' . $prescriptionImage->file_path) : null, // For patient-uploaded prescriptions
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'items' => $items,
                'meta'  => [
                    'page'     => $prescriptions->currentPage(),
                    'per_page' => $prescriptions->perPage(),
                    'total'    => $prescriptions->total(),
                ],
            ],
            'message' => '',
        ], 200);
    }

    // Endpoint: GET /api/patient/prescriptions/{prescription_id}
    public function getPrescriptionDetails($prescriptionId)
    {
        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Patient not found for this user.',
            ], 404);
        }

        $prescription = Prescription::with(['doctor.user', 'medications.medication'])
            ->where('id', $prescriptionId)
            ->where('patient_id', $patient->id)
            ->first();

        if (!$prescription) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Prescription not found.',
            ], 404);
        }

        $doctorUser = $prescription->doctor?->user;

        $medicines = $prescription->medications->map(function ($item) use ($prescription) {
            $med = $item->medication;
            return [
                'name'         => $med?->name,
                'dosage'       => $med?->dosage,
                'quantity'     => $item->boxes, // map boxes to quantity for API
                'instructions' => $item->instructions,
            ];
        })->values();

        if($prescription->source === 'patient_uploaded'){
            // For patient-uploaded prescriptions, doctor details might be null
            $doctorUser = null;
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'id'          => $prescription->id,
                    'prescription_image_url' => $prescription->prescriptionImage ? asset('storage/' . $prescription->prescriptionImage->file_path) : null,       
                ],
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'          => $prescription->id,
                'doctor_name' => $doctorUser ? 'Dr. ' . $doctorUser->full_name : null,
                'diagnosis'   => $prescription->diagnosis,
                'notes'       => $prescription->notes,
                'status'      => $prescription->status,
                'medicines'   => $medicines,        
            ],
        ], 200);
    }

    // Endpoint: POST /api/patient/prescriptions/upload
    public function uploadPaperPrescription(Request $request)
    {
        try {
            $validated = $request->validate([
                'image'    => 'required|image|max:5120', // 5MB
                'category' => 'required|string|max:50',
            ]);

            $user = Auth::user();

            $patient = Patient::where('user_id', $user->id)->first();

            if (!$patient) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Patient not found for this user.',
                ], 404);
            }

            $file = $validated['image'];
            
            // Ensure the storage directory exists
            if (!is_dir(storage_path('app/public/prescriptions'))) {
                mkdir(storage_path('app/public/prescriptions'), 0755, true);
            }

            $path = $file->store('prescriptions', 'public');

            if (!$path) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to store the uploaded file.',
                ], 500);
            }

            $upload = Upload::create([
                'user_id'   => $user->id,
                'patient_id' => $patient->id,
                'category'  => 'prescription',
                'file'      => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime'      => $file->getClientMimeType(),
            ]);

            // Create a prescription record linked to this upload
            $prescription = Prescription::create([
                'consultation_id'       => null,
                'doctor_id'             => null,
                'patient_id'            => $patient->id,
                'diagnosis'             => null,
                'notes'                 => null,
                'source'                => 'patient_uploaded',
                'status'                => 'created',
                'prescription_image_id' => $upload->id,
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'upload_id'       => $upload->id,
                    'prescription_id' => $prescription->id,
                ],
                'message' => 'Prescription uploaded successfully',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to upload prescription.',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Patient: Send prescription to a selected pharmacy (creates an order).
     *
     * Endpoint: POST /api/patient/prescriptions/{prescription_id}/send
     */
    public function sendPrescriptionToPharmacy(Request $request, $prescriptionId)
    {
        $validated = $request->validate([
            'pharmacy_id' => 'required|integer|exists:pharmacists,id',
        ]);

        $user = Auth::user();
        $patient = $user->patient;
        if (!$patient) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Patient not found for this user.',
            ], 404);
        }


        $prescription = Prescription::where('id', $prescriptionId)
            ->where('patient_id', $patient->id)
            ->first();

        if (!$prescription) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Prescription not found.',
            ], 404);
        }
        //pharmacy closed check depending on from ,to fields in pharmacies table
        $pharmacist = Pharmacist::find($validated['pharmacy_id']);
        if (!$pharmacist || !$pharmacist->isOpen()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Selected pharmacy is currently closed ,please choose another one.',
            ], 400);
        }

        // Create order for this prescription & pharmacy
        $order = Order::create([
            'prescription_id' => $prescription->id,
            'patient_id'      => $patient->id,
            'pharmacist_id'   => $validated['pharmacy_id'],
            'status'          => 'sent_to_pharmacy',
        ]);

        // Mark prescription as sent to pharmacy
        $prescription->update([
            'pharmacist_id' => $validated['pharmacy_id'],
            'status' => 'sent_to_pharmacy',
        ]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'order_id'        => $order->id,
                'prescription_id' => $prescription->id,
                'pharmacy_id'     => $validated['pharmacy_id'],
                'status'          => 'sent_to_pharmacy',
            ],
            'message' => 'Prescription sent to pharmacy',
        ], 200);
    }
    public function getPrescriptionStatus($prescription_id)
    {
        $prescription = Prescription::find($prescription_id);

        if (!$prescription) {
            return response()->json([
                'status' => 'error',
                'message' => 'Prescription not found'
            ], 404);
        }
        // Fetch the latest order associated with the prescription
        $order = Order::with('pharmacist.pharmacy')
            ->where('prescription_id', $prescription_id)
            ->latest()
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'No order found for this prescription'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'pharmacy' => [
                    'id' => $order->pharmacist->pharmacy->id ?? null,
                    'name' => $order->pharmacist->pharmacy->name ?? null,
                ],
            ],
            'message' => ''
        ], 200);
    }

    /**
     * Patient: View prescription pricing (read-only)
     * GET /api/patient/prescriptions/{id}/update-price
     */
    // public function viewPrescriptionPricing($prescriptionId)
    // {
    //     $user = Auth::user();
    //     $patient = Patient::where('user_id', $user->id)->first();

    //     if (!$patient) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Patient not found for this user.',
    //         ], 404);
    //     }

    //     $prescription = Prescription::with(['pharmacist', 'medications.medication'])
    //         ->where('id', $prescriptionId)
    //         ->where('patient_id', $patient->id)
    //         ->first();
        

    //     if (!$prescription) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Prescription not found or not authorized.',
    //         ], 404);
    //     }

    //     // Check if prescription has been priced
    //     if ($prescription->status !== 'accepted') {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Prescription pricing not available yet.',
    //         ], 404);
    //     }
    //     $pharmacy=$prescription->pharmacist->map(function($pharmacist){
    //         return [
    //             'id'=>$pharmacist->id,
    //             'name'=>$pharmacist->name ?? null,
    //         ];
    //     });
    //     $items = $prescription->medications->map(function ($medication) {
    //         return [
    //             'medicine_name' => $medication->medicine_name ?? $medication->medication->name ?? 'Unknown',
    //             'quantity' => $medication->quantity ?? 1,
    //             'price' => (float) ($medication->price ?? 0),
    //         ];
        
    //     })->values();

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => [
    //             'prescription_id' => $prescription->id,
    //             'status' => $prescription->status,
    //             'source' => $prescription->source,
    //             'pharmacy' => $pharmacy,
    //             'items' => $items,
    //             'total_price' => (float) ($prescription->total_price ?? 0),
    //         ],
    //     ], 200);
    // }

    /**
     * Patient: Get all prescriptions with pricing
     * GET /api/patient/prescriptions/pricing
     */
    // public function getPrescriptionsWithPricing(Request $request)
    // {
    //     $validated = $request->validate([
    //         'page' => 'sometimes|integer|min:1',
    //         'per_page' => 'sometimes|integer|min:1|max:100',
    //     ]);

    //     $user = Auth::user();
    //     $patient = Patient::where('user_id', $user->id)->first();

    //     if (!$patient) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Patient not found for this user.',
    //             'data' => [],
    //         ], 404);
    //     }
    


    //     $perPage = $request->get('per_page');

    //     // Get prescriptions that have been priced (status = 'accepted')
    //     $prescriptions = Prescription::where('patient_id', $patient->id)
    //         ->where('status', 'accepted')
    //         ->orderByDesc('created_at')
    //         ->paginate($perPage)->appends($request->query());
    //     if ($prescriptions->isEmpty()) {
    //         return response()->json([
    //             'status' => 'empty',
    //             'message' => 'No prescriptions with pricing found.',
    //             'data' => [],
    //             'meta' => [
    //                 'current_page' => $prescriptions->currentPage(),
    //                 'per_page' => $prescriptions->perPage(),
    //                 'total_pages' => $prescriptions->lastPage(),
    //                 'total_items' => $prescriptions->total(),
    //             ],
    //         ], 200);
    //     }

    //     $data = $prescriptions->getCollection()->map(function ($prescription) {
    //         // Get the latest order for this prescription
    //         $order = Order::with(['pharmacist', 'items.medication'])
    //             ->where('prescription_id', $prescription->id)
    //             ->orderByDesc('created_at')
    //             ->first();

    //         if (!$order || !$order->pharmacist) {
    //             return null;
    //         }

    //         // Get pharmacy info
    //         $pharmacy = [
    //             'id' => $order->pharmacist->id,
    //             'name' => $order->pharmacist->pharmacy_name ?? 'Unknown Pharmacy',
    //         ];

    //         // Get order medications (items)
    //         $items = $order->items->map(function ($orderMedication) {
    //             $quantity = $orderMedication->total_quantity ?? 1;
    //             $totalPrice = (float) ($orderMedication->total_price ?? 0);
    //             // Calculate unit price
    //             $unitPrice = $quantity > 0 ? $totalPrice / $quantity : $totalPrice;
                
    //             return [
    //                 'medicine_name' => $orderMedication->medication->name ?? 'Unknown',
    //                 'quantity' => $quantity,
    //                 'price' => $unitPrice,
    //             ];
    //         })->values();

    //         // Calculate total quantity
    //         $totalQuantity = $order->items->sum('total_quantity');

    //         // Map source: 'patient_uploaded' to 'paper', 'doctor_written' to 'doctor'
    //         $source = $prescription->source === 'patient_uploaded' ? 'paper' : 'doctor';

    //         return [
    //             'prescription_id' => $prescription->id,
    //             'status' => $prescription->status,
    //             'source' => $source,
    //             'pharmacy' => $pharmacy,
    //             'items' => $items,
    //             'total_quantity' => $totalQuantity,
    //             'total_price' => (float) ($prescription->total_price ?? 0),
    //         ];
    //     })->filter()->values();

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $data,
    //         'meta' => [
    //             'current_page' => $prescriptions->currentPage(),
    //             'per_page' => $prescriptions->perPage(),
    //             'total_pages' => $prescriptions->lastPage(),
    //             'total_items' => $prescriptions->total(),
    //         ],
    //     ], 200);
    // }
}
