<?php

namespace App\Http\Controllers\Api;

use App\Models\Doctor;
use App\Models\Rating;
use Illuminate\Http\Request;
use App\Services\DoctorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePrescriptionRequest;

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

    public function createPrescription(CreatePrescriptionRequest $request)
    {
        $validated = $request->validated();

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
    public function getDoctorRatings(Request $request, $doctorId)
    
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50'
        ]);

        try {
            $doctor = Doctor::findOrFail($doctorId);
            
            $query = Rating::with('user')
                ->where('target_type', 'doctor')
                ->where('target_id', $doctorId)
                ->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 10);
            $ratings = $query->paginate($perPage);

            $data = $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'stars' => $rating->stars,
                    'user_name' => $rating->user->full_name ?? 'Unknown',
                    'created_at' => $rating->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'doctor_id' => $doctorId,
                    'average_rating' => $doctor->rating_avg,
                    'total_ratings' => $ratings->total(),
                    'ratings' => $data
                ],
                'meta' => [
                    'current_page' => $ratings->currentPage(),
                    'last_page' => $ratings->lastPage(),
                    'per_page' => $ratings->perPage(),
                    'total' => $ratings->total()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get doctor profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $doctor = $user->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile retrieved successfully',
            'data' => [
                'id' => $doctor->id,
                'full_name' => $user->full_name,
                'specialization' => $doctor->specialization ? $doctor->specialization->name : null,
                'phone' => $user->phone,
                'email' => $user->email,
                'from' => $doctor->from,
                'to' => $doctor->to,
                'consultation_fee' => $doctor->consultation_fee,
                'bank_account' => $doctor->bank_account,
                'certificate_file' => $doctor->certificate_file_id ? asset('/storage/' . $doctor->certificateFile->file_path) : null,
                'rating_avg' => $doctor->rating_avg,
            ]
        ]);
    }

    /**
     * Update doctor profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'from' => 'sometimes|date_format:H:i',
            'to' => 'sometimes|date_format:H:i',
            'consultation_fee' => 'sometimes|numeric|min:0',
            'bank_account' => 'sometimes|string|max:255',
        ]);

        $user = $request->user();
        $doctor = $user->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found'
            ], 404);
        }

        if ($request->has('from')) {
            $doctor->from = $request->from;
        }
        if ($request->has('to')) {
            $doctor->to = $request->to;
        }
        if ($request->has('consultation_fee')) {
            $doctor->consultation_fee = $request->consultation_fee;
        }
        if ($request->has('bank_account')) {
            $doctor->bank_account = $request->bank_account;
        }
        $doctor->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => [
                'doctor' => [
                    'id' => $doctor->id,
                    'from' => $doctor->from,
                    'to' => $doctor->to,
                    'consultation_fee' => $doctor->consultation_fee,
                    'bank_account' => $doctor->bank_account,
                ]
            ]
        ]);
    }
}
