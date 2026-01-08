<?php

namespace App\Http\Controllers\Api;

use App\Models\Doctor;
use App\Services\ConsultationService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ConsultationController extends Controller
{
    protected $consultationService;

    public function __construct(ConsultationService $consultationService)
    {
        $this->consultationService = $consultationService;
    }

    public function bookConsultation(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'call_type' => 'required|in:call_now,schedule',
            'scheduled_at' => 'nullable|date',
        ]);

        try {
            $consultation = $this->consultationService->bookConsultation($validated);
            
            // Load doctor for response
            $doctor = Doctor::with('user')->find($consultation->doctor_id);

            $responseData = [
                'status' => 'success',
                'message' => 'Consultation booked successfully.',
                'data' => [
                    'consultation_id' => $consultation->id,
                    'doctor_id' => $consultation->doctor_id,
                    'patient_id' => $consultation->patient_id,
                    'call_type' => $consultation->type,
                    'scheduled_at' => $consultation->scheduled_at ? $consultation->scheduled_at->toIso8601String() : null,
                    'status' => $consultation->status,
                ],
            ];
            // Provide doctor's phone number for call_now consultations
            if ($consultation->type === 'call_now' || $consultation->type === 'schedule') {
                $responseData['data']['doctor_phone'] = $doctor->user->phone;
            }

            return response()->json($responseData, 201);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
    public function startConsultation($id)
    {
        try {
            $result = $this->consultationService->startConsultation($id);
            $consultation = $result['consultation'];

            if ($result['is_joining']) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Joining already-started consultation.',
                    'data' => [
                        'consultation_id' => $consultation->id,
                        'role' => $result['role'],
                        'status' => $consultation->status,
                    ]
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Consultation started successfully.',
                'data' => [
                    'consultation_id' => $consultation->id,
                    'role' => $result['role'],
                    'status' => 'in_progress',
                ]
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function endConsultation($id)
    {
        try {
            $result = $this->consultationService->endConsultation($id);
            $consultation = $result['consultation'];

            return response()->json([
                'status' => 'success',
                'message' => 'Consultation ended successfully.',
                'data' => [
                    'consultation_id' => $consultation->id,
                    'ended_by' => $result['ended_by'],
                    'status' => $consultation->status,
                ]
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
