<?php

namespace App\Http\Controllers\Api;

use App\Services\DoctorService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DoctorController extends Controller
{
    protected $doctorService;

    public function __construct(DoctorService $doctorService)
    {
        $this->doctorService = $doctorService;
    }

    public function getDoctorsBySpecialization(Request $request)
    {
        $validated = $request->validate([
            'specialization_id' => 'required|integer|exists:specializations,id',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $perPage = $request->get('per_page', 10);
            $result = $this->doctorService->getDoctorsBySpecialization($validated['specialization_id'], $perPage);

            if (empty($result['doctors'])) {
                return response()->json([
                    'status' => 'empty',
                    'message' => 'No doctors available for this specialization yet.',
                    'specialization' => $result['specialization'],
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $result['doctors'],
                'meta' => $result['meta'],
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
    public function getAvailableSlots(Request $request, $doctorId)
    {
        $validated = $request->validate([
            'date' => 'sometimes|date',
        ]);

        try {
            $date = $request->query('date');
            $result = $this->doctorService->getAvailableSlots($doctorId, $date);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    public function getDoctorSchedules(Request $request)
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,scheduled,in_progress,completed,cancelled',
            'date' => 'sometimes|date',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latest' => 'sometimes|boolean',
        ]);

        try {
            $filters = [];
            if ($request->filled('status')) {
                $filters['status'] = $validated['status'];
            }
            if ($request->filled('date')) {
                $filters['date'] = $validated['date'];
            }

            $perPage = $request->get('per_page', 10);
            $latest = $request->boolean('latest');

            $result = $this->doctorService->getDoctorSchedules($filters, $perPage, $latest);

            return response()->json([
                'status' => 'success',
                'data' => $result['data'],
                'meta' => $result['meta'],
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function createPrescription(Request $request)
    {
        $validated = $request->validate([
            'consultation_id' => 'required|integer|exists:consultations,id',
            'diagnosis'       => 'required|string|max:500',
            'notes'           => 'nullable|string',
            'medicines'       => 'required|array|min:1',
            'medicines.*.name'         => 'required|string|max:255',
            'medicines.*.dosage'       => 'required|string|max:255',
            'medicines.*.boxes'        => 'required',
            'medicines.*.instructions' => 'nullable|string|max:500',
        ]);

        try {
            $prescription = $this->doctorService->createPrescription($validated);

            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'prescription_id' => $prescription->id,
                    'status'          => $prescription->status,
                    'issued_at'       => $prescription->created_at?->toIso8601String(),
                ],
                'message' => 'Prescription created',
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
