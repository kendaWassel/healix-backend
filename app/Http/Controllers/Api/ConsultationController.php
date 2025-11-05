<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Consultation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;


class ConsultationController extends Controller
{
    public function getPatientScheduledConsultations(Request $request)
    {

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            //add pagination for getPatientScheduledConsultations
            $perPage = $request->get('per_page', 10);
            $consultations = Consultation::where('patient_id', Auth::id())->paginate($perPage)->appends($request->query());
            if ($consultations->isEmpty()) {
                return response()->json([
                    'status' => 'empty',
                    'message' => 'No scheduled consultations found',
                    'data' => []
                ], 200);
            }
            // Map data
            $consultations->getCollection()->map(function ($consultation) {
                $doctor = $consultation->doctor;
                $doctorImage = null;
                if ($doctor && !empty($doctor->doctor_image_id)) {
                    $upload = Upload::find($doctor->doctor_image_id);
                    if ($upload && $upload->file_path) {
                        $doctorImage = asset('storage/' . ltrim($upload->file_path, '/'));
                    }
                }

                return [
                    'id' => $consultation->id,
                    'doctor_id' => $doctor ? $doctor->id : null,
                    'doctor_name' => $doctor ? 'Dr. ' . $doctor->user?->full_name : null,
                    'doctor_image' => $doctorImage,
                    'type' => $consultation->call_type,
                    'scheduled_at' => $consultation->scheduled_at ? $consultation->scheduled_at->toIso8601String() : null,
                    'status' => $consultation->status,
                ];
            });
            // Return paginated response
            $meta = [
                'current_page' => $consultations->currentPage(),
                'last_page' => $consultations->lastPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $consultations->getCollection()->toArray(),
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

    public function bookConsultation(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'type' => 'required|in:call_now,schedule_later',
            'scheduled_at' => 'nullable|date',
        ]);

        $doctor = Doctor::find($validated['doctor_id']);
        if (!$doctor) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Doctor not found']
            ,404);
        }

        // Get the authenticated patient ID
        $patientId = Auth::id();
        if (!$patientId) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Unauthenticated']
            , 401);
        }

        // If schedule_later require scheduled_at
        if ($validated['type'] === 'schedule_later' && empty($validated['scheduled_at'])) {
            return response()->json([
                'status' => 'error', 
                'message' => 'scheduled_at is required for schedule_later']
            , 422);
        }

        // If schedule_later validate availability within doctor's hours
        if (!empty($validated['scheduled_at'])) {
            $scheduled = Carbon::parse($validated['scheduled_at']);// like 2024-12-01 14:00:00
            $time = $scheduled->format('H:i');
            if (!empty($doctor->from) && !empty($doctor->to)) {
                if (!($time >= $doctor->from && $time <= $doctor->to)) {
                    return response()->json([
                        'status' => 'error', 
                        'message' => 'The scheduled time is outside the doctor\'s available hours']
                    , 422);
                }
            }
        }

        // For call_now check doctor availability now
        if ($validated['type'] === 'call_now') {
            $now = Carbon::now()->format('H:i');
            if (!empty($doctor->from) && !empty($doctor->to)) {
                if (!($now >= $doctor->from && $now <= $doctor->to)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Doctor is not available now'
                    ], 409);
                }
            }
        }

        // Prevent double-booking: simple equality check on scheduled_at
        if (!empty($validated['scheduled_at'])) {
            $exists = Consultation::where('doctor_id', $doctor->id)
                ->where('scheduled_at', Carbon::parse($validated['scheduled_at']))
                ->whereNotIn('status', ['cancelled'])
                ->exists();
            if ($exists) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Requested time slot is already booked']
                , 409);
            }
        }

        try {
            DB::beginTransaction();

            $consultation = Consultation::create([
                'patient_id' => $patientId,
                'doctor_id' => $doctor->id,
                'call_type' => $validated['type'] === 'call_now' ? 'call_now' : 'schedule',
                'status' => $validated['type'] === 'call_now' ? 'in_progress' : 'scheduled',
                'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Consultation created',
                'data' => [
                    'consultation_id' => $consultation->id,
                    'doctor_id' => $consultation->doctor_id,
                    'patient_id' => $consultation->patient_id,
                    'type' => $validated['type'],
                    'scheduled_at' => $consultation->scheduled_at ? $consultation->scheduled_at->toIso8601String() : null,
                    'status' => $consultation->status,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error', 
                'message' => 'Failed to create consultation', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Return doctor's phone number so patient can call externally.
    public function startCall($id)
    {
        $consultation = Consultation::with('doctor.user')
            ->where('id', $id)
            ->where('patient_id', auth()->id())
            ->first();

        if (!$consultation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Consultation not found or not authorized.'
            ], 404);
        }

        $doctor = $consultation->doctor;
        $user = $doctor->user;

        return response()->json([
            'status' => 'success',
            'message' => 'Call started successfully.',
            'doctor_name' => 'Dr. ' . $user->full_name,
            'doctor_phone' => $user->phone,
        ]);
    }
}
