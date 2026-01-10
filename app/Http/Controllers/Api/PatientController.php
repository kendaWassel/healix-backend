<?php

namespace App\Http\Controllers\Api;

use App\Services\PatientService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadRequest;

class PatientController extends Controller
{
    protected $patientService;

    public function __construct(PatientService $patientService)
    {
        $this->patientService = $patientService;
    }

    public function getPatientScheduledConsultations(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $perPage = $request->get('per_page', 10);
            $consultations = $this->patientService->getScheduledConsultations($perPage);
            
            if ($consultations->isEmpty()) {
                return response()->json([
                    'status' => 'empty',
                    'message' => 'No scheduled consultations found',
                    'data' => []
                ], 200);
            }
            
            $data = $consultations->getCollection()->map(function ($consultation) {
                return $this->patientService->formatConsultationData($consultation);
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
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
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

        try {
            $perPage = $request->get('per_page', 10);
            $prescriptions = $this->patientService->getPrescriptions($perPage);

            if ($prescriptions->isEmpty()) {
                return response()->json([
                    'status'  => 'empty',
                    'message' => 'No prescriptions found for this patient.',
                    'data'    => [
                        'items' => [],
                        'meta'  => [
                            'page'     => $prescriptions->currentPage(),
                            'last_page' => $prescriptions->lastPage(),
                            'per_page' => $prescriptions->perPage(),
                            'total'    => $prescriptions->total(),
                        ],
                    ],
                ], 200);
            }

            $items = $prescriptions->getCollection()->map(function ($prescription) {
                return $this->patientService->formatPrescriptionData($prescription);
            })->values();

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'items' => $items,
                    'meta'  => [
                        'page'     => $prescriptions->currentPage(),
                        'last_page' => $prescriptions->lastPage(),
                        'per_page' => $prescriptions->perPage(),
                        'total'    => $prescriptions->total(),
                    ],
                ],
                'message' => 'Prescriptions retrieved successfully.',
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    // Endpoint: GET /api/patient/prescriptions/{prescription_id}
    public function getPrescriptionDetails($prescriptionId)
    {
        try {
            $data = $this->patientService->getPrescriptionDetails($prescriptionId);
            return response()->json([
                'status' => 'success',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    // Endpoint: POST /api/patient/prescriptions/upload
    public function uploadPaperPrescription(UploadRequest $request)
    {
        try {
            $validated = $request->validated();

            $data = $this->patientService->uploadPaperPrescription($validated);

            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'upload_id'       => $data['upload_id'],
                    'prescription_id' => $data['prescription_id'],
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
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
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

        try {
            $data = $this->patientService->sendPrescriptionToPharmacy($prescriptionId, $validated['pharmacy_id']);

            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'order_id'        => $data['order_id'],
                    'prescription_id' => $data['prescription_id'],
                    'pharmacy_id'     => $data['pharmacy_id'],
                    'status'          => $data['status'],
                ],
                'message' => 'Prescription sent to pharmacy',
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
    public function getPrescriptionStatus($prescription_id)
    {
        try {
            $data = $this->patientService->getPrescriptionStatus($prescription_id);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'message' => ''
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
    /**
     * Patient: Get all prescriptions with pricing
     * GET /api/patient/view-prescriptions-with-pricing
     */
    public function getPrescriptionsWithPricing(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $perPage = $request->get('per_page', 10);
            $prescriptions = $this->patientService->getPrescriptionsWithPricing($perPage);

            if ($prescriptions->isEmpty()) {
                return response()->json([
                    'status' => 'empty',
                    'message' => 'No prescriptions with pricing found.',
                    'data' => [],
                    'meta' => [
                        'current_page' => $prescriptions->currentPage(),
                        'per_page' => $prescriptions->perPage(),
                        'last_page' => $prescriptions->lastPage(),
                        'total' => $prescriptions->total(),
                    ],
                ], 200);
            }

            $data = $prescriptions->getCollection()->map(function ($prescription) {
                return $this->patientService->formatPrescriptionWithPricing($prescription);
            })->filter()->values();

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'current_page' => $prescriptions->currentPage(),
                    'per_page' => $prescriptions->perPage(),
                    'last_page' => $prescriptions->lastPage(),
                    'total' => $prescriptions->total(),
                ],
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Patient: View Order & Delivery Status
     * 
     * Endpoint: GET /api/patient/orders/delivery-info
     */
    public function getDeliveryInfo(Request $request)
    {
        $validated = $request->validate([
            'page'     => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $perPage = $request->get('per_page', 10);
            $orders = $this->patientService->getDeliveryInfo($perPage);

            $data = $orders->getCollection()->map(function ($order) {
                return $this->patientService->formatDeliveryInfo($order);
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
        } catch (\Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Patient: Get delivery info for a specific order
     * 
     * Endpoint: GET /api/patient/orders/{order_id}/delivery-info
     */
    public function getOrderDeliveryInfo($orderId)
    {
        try {
            $order = $this->patientService->getOrderDeliveryInfo($orderId);
            $data = $this->patientService->formatDeliveryInfo($order);

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
    public function getPatientScheduledCareProviders(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $perPage = $request->get('per_page', 10);
            $providers = $this->patientService->getPatientScheduledCareProviders($perPage);

            $data = $providers->getCollection()->map(function ($provider) {
                return $this->patientService->formatPatientScheduledCareProviders($provider);
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'current_page' => $providers->currentPage(),
                    'last_page' => $providers->lastPage(),
                    'per_page' => $providers->perPage(),
                    'total' => $providers->total()
                ],
            ]);
        }

        catch (\Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Request a new care provider for a cancelled home visit
     * 
     * Endpoint: POST /api/patient/home-visits/{visit_id}/request-new-care-provider
     */
    public function requestNewCareProvider(Request $request, $visitId)
    {
        $validated = $request->validate([
            'scheduled_at' => 'required', // YYYY-MM-DD HH:MM:S   
        ]);

        try {
            $data = $this->patientService->requestNewCareProvider(
                $visitId,
                $validated['scheduled_at']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'New care provider requested successfully.',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}

