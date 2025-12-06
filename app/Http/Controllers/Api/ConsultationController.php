<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Patient;
use App\Models\Consultation;
use App\Events\ConsultationBooked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Notifications\ConsultationRequestedNotification;


class ConsultationController extends Controller
{

    public function bookConsultation(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'call_type' => 'required|in:call_now,schedule',
            'scheduled_at' => 'nullable|date',
        ]);

        $doctor = Doctor::find($validated['doctor_id']);
        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor not found'
            ], 404);
        }

        $user = Auth::user(); //returns the authenticated user
        // dd($user);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ], 401);
        }


        // Get patient model from authenticated user
        $patient = $user->patient;
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient profile not found'
            ], 404);
        }

        if ($validated['call_type'] === 'schedule' && empty($validated['scheduled_at'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'scheduled_at is required for schedule_later'
            ], 422);
        }

        //validate booking time within doctor's available hours for schedule_later
        if (!empty($validated['scheduled_at'])) {
            $scheduled = Carbon::parse($validated['scheduled_at']);
            $time = $scheduled->format('H:i');
            if (!empty($doctor->from) && !empty($doctor->to)) {
                if (!($time >= $doctor->from && $time <= $doctor->to)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'The scheduled time is outside the doctor\'s available hours'
                    ], 422);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Doctor availability hours are not set'
                ], 422);
            }
        }

        // validate doctor's availability for call_now based on working hours
        if ($validated['call_type'] === 'call_now') {
            $now = Carbon::now()->format('H:i');

            if (!empty($doctor->from) && !empty($doctor->to)) {
                if (!($now >= $doctor->from && $now <= $doctor->to)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Doctor is not available now'
                    ], 409);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Doctor availability hours are not set'
                ], 409);
            }

            $activeConsultation = Consultation::where('doctor_id', $doctor->id)
                ->where('status', 'in_progress')
                ->first();

            if ($activeConsultation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Doctor is currently busy with another consultation.'
                ], 409);
            }
        }

        if (!empty($validated['scheduled_at'])) {
            $exists = Consultation::where('doctor_id', $doctor->id)
                ->where('scheduled_at', Carbon::parse($validated['scheduled_at']))
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Requested time slot is already booked'
                ], 409);
            }
        }

        try {
            DB::beginTransaction();

            // Use patient->id (not user id)
            $consultation = Consultation::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'type' => $validated['call_type'],
                'status' => 'pending',
                'start_time' => $validated['call_type'] === 'call_now' ? Carbon::now() : null,
                'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null,
            ]);

            DB::commit();

            // Load relationships for event and notification
            $consultation->load(['patient', 'doctor.user']);
            $doctor->loadMissing('user');

            // Send database notification to doctor
            if ($doctor->user) {
                $doctor->user->notify(
                    new ConsultationRequestedNotification($consultation, $patient)
                );
            }

            // Get patient user for event
            $patientUser = $consultation->patient;
            if (method_exists($patient, 'user') && $patient->user) {
                $patientUser = $patient->user;
            }

            // Broadcast real-time event to doctor
            if ($doctor->user) {
                event(new ConsultationBooked($consultation, $patientUser, $doctor->user));
            }

            $responseData = [
                'status' => 'success',
                'message' => 'Consultation created',
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
                $responseData['doctor_phone'] = $doctor->user->phone;
            }

            return response()->json($responseData, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create consultation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function startConsultation($id)
    {
        $user = Auth::user();

        // Determine if user is doctor or patient
        $doctor = $user->doctor;
        $patient = $user->patient;

        if (!$doctor && !$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Fetch consultation based on role
        $consultation = Consultation::where('id', $id)
            ->when($doctor, fn($q) => $q->where('doctor_id', $doctor->id))
            ->when($patient, fn($q) => $q->where('patient_id', $patient->id))
            ->first();

        if (!$consultation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Consultation not found.'
            ], 404);
        }

        // If already started → user just joins
        if ($consultation->status === 'in_progress') {
            return response()->json([
                'status' => 'success',
                'message' => 'Joining already-started consultation.',
                'data' => [
                    'consultation_id' => $consultation->id,
                    'role' => $doctor ? 'doctor' : 'patient',
                    'status' => $consultation->status,
                ]
            ]);
        }

        // Pending → this user is the first to start
        // Allowed types are 'schedule' and 'call_now'
        if (!in_array($consultation->type, ['schedule', 'call_now'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid consultation type.'
            ], 422);
        }

        // Only pending consultations can be started
        if ($consultation->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Consultation is not in a state to be started.'
            ], 409);
        }

        // If it's a scheduled consultation, ensure scheduled_at has arrived
        if ($consultation->type === 'schedule') {
            if (empty($consultation->scheduled_at)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Scheduled time is missing for this consultation.'
                ], 422);
            }

            $now = Carbon::now();
            $scheduled = Carbon::parse($consultation->scheduled_at);
            // if ($now->lt($scheduled)|| $now->lte($scheduled)) {
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'It is not time to start the scheduled consultation yet.'
            //     ], 409);
            // }
        }
        // Start consultation
        $consultation->update([
            'status' => 'in_progress',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Consultation started successfully.',
            'data' => [
                'consultation_id' => $consultation->id,
                'role' => $doctor ? 'doctor' : 'patient',
                'status' => 'in_progress',
            ]
        ]);
    }

    public function endConsultation($id)
    {
        $user = Auth::user();
        
        // Determine if user is doctor or patient
        $doctor = $user->doctor;
        $patient = $user->patient;

        if (!$doctor && !$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.'
            ], 403);
        }

        // Fetch consultation based on role
        $consultation = Consultation::where('id', $id)
            ->when($doctor, fn($q) => $q->where('doctor_id', $doctor->id))
            ->when($patient, fn($q) => $q->where('patient_id', $patient->id))
            ->first();

        if (!$consultation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Consultation not found or not authorized.'
            ], 404);
        }

        if ($consultation->status !== 'in_progress') {
            return response()->json([
                'status' => 'error',
                'message' => 'Consultation is not in progress.'
            ], 409);
        }

        // End the consultation
        $consultation->status = 'completed';
        // $consultation->end_time = Carbon::now();
        $consultation->save();
        $currentRole = $doctor ? 'doctor' : 'patient';

        return response()->json([
            'status' => 'success',
            'message' => 'Consultation ended successfully.',
            'data' => [
                'consultation_id' => $consultation->id,
                'ended_by' => $currentRole,
                'status' => $consultation->status,
            ]
        ]);
    }
}
