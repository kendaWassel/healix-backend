<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Patient;
use App\Models\Consultation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Notifications\ConsultationRequestedNotification;


class ConsultationController extends Controller
{
    // public function bookConsultation(Request $request)
    // {
    //     $validated = $request->validate([
    //         'doctor_id' => 'required|exists:doctors,id',
    //         'type' => 'required|in:call_now,schedule_later',
    //         'scheduled_at' => 'nullable|date',
    //     ]);

    //     $doctor = Doctor::find($validated['doctor_id']);
    //     if (!$doctor) {
    //         return response()->json([
    //             'status' => 'error', 
    //             'message' => 'Doctor not found']
    //         ,404);
    //     }

    //     // Get the authenticated patient ID
    //     $patientId = Auth::id();
    //     if (!$patientId) {
    //         return response()->json([
    //             'status' => 'error', 
    //             'message' => 'Unauthenticated']
    //         , 401);
    //     }

    //     // If schedule_later require scheduled_at
    //     if ($validated['type'] === 'schedule_later' && empty($validated['scheduled_at'])) {
    //         return response()->json([
    //             'status' => 'error', 
    //             'message' => 'scheduled_at is required for schedule_later']
    //         , 422);
    //     }

    //     // If schedule_later validate availability within doctor's hours
    //     if (!empty($validated['scheduled_at'])) {
    //         $scheduled = Carbon::parse($validated['scheduled_at']);// like 2024-12-01 14:00:00
    //         $time = $scheduled->format('H:i');
    //         if (!empty($doctor->from) && !empty($doctor->to)) {
    //             if (!($time >= $doctor->from && $time <= $doctor->to)) {
    //                 return response()->json([
    //                     'status' => 'error', 
    //                     'message' => 'The scheduled time is outside the doctor\'s available hours']
    //                 , 422);
    //             }
    //         }
    //     }

    //     // For call_now check doctor availability now
    //     if ($validated['type'] === 'call_now') {
    //         if (!empty($doctor->from) && !empty($doctor->to)) {
    //             $now = Carbon::now();
                
    //             // Parse doctor's available hours (handle both H:i and H:i:s formats)
    //             $fromTime = Carbon::parse($doctor->from)->format('H:i');
    //             $toTime = Carbon::parse($doctor->to)->format('H:i');
    //             $currentTime = $now->format('H:i');
                
    //             // Check if current time is within doctor's available hours
    //             if (!($currentTime >= $fromTime && $currentTime <= $toTime)) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Doctor is not available now'
    //                 ], 409);
    //             }
    //         } 
    //         $existingCallNow = Consultation::where('doctor_id', $doctor->id)
    //             ->where('patient_id', $patientId)
    //             ->where('type', 'call_now')
    //             ->where('status', 'in_progress')
    //             ->exists();
    //         if ($existingCallNow) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'You have an existing call now already'
    //             ], 409);

    //         }
    //     }
        

    //     // Prevent double-booking: simple equality check on scheduled_at
    //     if (!empty($validated['scheduled_at'])) {
    //         $exists = Consultation::where('doctor_id', $doctor->id)
    //             ->where('scheduled_at', Carbon::parse($validated['scheduled_at']))
    //             ->whereNotIn('status', ['cancelled'])
    //             ->exists();
    //         if ($exists) {
    //             return response()->json([
    //                 'status' => 'error', 
    //                 'message' => 'Requested time slot is already booked']
    //             , 409);
    //         }
    //     }

    //     try {
    //         DB::beginTransaction();

    //         $consultation = Consultation::create([
    //             'patient_id' => $patientId,
    //             'doctor_id' => $doctor->id,
    //             'type' => $validated['type'] === 'call_now' ? 'call_now' : 'schedule',
    //             'status' => $validated['type'] === 'call_now' ? 'in_progress' : 'scheduled',
    //             'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null,
    //         });

    //         DB::commit();

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Consultation created',
    //             'data' => [
    //                 'consultation_id' => $consultation->id,
    //                 'doctor_id' => $consultation->doctor_id,
    //                 'patient_id' => $consultation->patient_id,
    //                 'type' => $validated['type'],
    //                 'scheduled_at' => $consultation->scheduled_at ? $consultation->scheduled_at->toIso8601String() : null,
    //                 'status' => $consultation->status,
    //             ]
    //         ], 201);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'status' => 'error', 
    //             'message' => 'Failed to create consultation', 
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    
    public function bookConsultation(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'call_type' => 'required|in:call_now,schedule_later',
            'scheduled_at' => 'nullable|date',
        ]);

        $doctor = Doctor::find($validated['doctor_id']);
        if (!$doctor) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Doctor not found'
            ], 404);
        }

        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Get patient model from authenticated user
        $patient = Auth::user()->patient;

        if (!$patient) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Patient profile not found'
            ], 404);
        }

        if ($validated['call_type'] === 'schedule_later' && empty($validated['scheduled_at'])) {
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
            $now = Carbon::now('Asia/Kolkata')->format('H:i');

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
                'status' => $validated['call_type'] === 'call_now' ? 'pending' : 'scheduled',
                'start_time' => $validated['call_type'] === 'call_now' ? Carbon::now() : null,
                'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null,
                'end_time' => null,
            ]);

            DB::commit();

            $doctor->loadMissing('user');
            if ($doctor->user) {
                $doctor->user->notify(
                    new ConsultationRequestedNotification($consultation, $user)
                );
            }

            $responseData = [
                'status' => 'success',
                'message' => 'Consultation created',
                'data' => [
                    'consultation_id' => $consultation->id,
                    'doctor_id' => $consultation->doctor_id,
                    'patient_id' => $consultation->patient_id,
                    'call_type' => $consultation->type,
                    'start_time' => $consultation->start_time ? $consultation->start_time->toIso8601String() : null,
                    'scheduled_at' => $consultation->scheduled_at ? $consultation->scheduled_at->toIso8601String() : null,
                    'status' => $consultation->status,
                ],
            ];
            // Provide doctor's phone number for call_now consultations
            if ($consultation->type === 'call_now') {
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

    public function startCall($id)
    {
        // Use patient->id instead of user id
        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient profile not found.'
            ], 404);
        }

        $consultation = Consultation::where('id', $id)
            ->where('patient_id', $patient->id)
            ->first();

        if (!$consultation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Consultation not found or not authorized.'
            ], 404);
        }

        // validate state based on consultation type
        if ($consultation->type === 'schedule_later') {
            if ($consultation->status !== 'scheduled') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Consultation is not in a scheduled state to start the call.'
                ], 409);
            }
            // validate if it's time to start the call
            $now = Carbon::now();
            if ($now->lt($consultation->scheduled_at)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'It is not time to start the call yet.'
                ], 409);
            }
        } else if ($consultation->type === 'call_now') {
            // In case of call_now, the session usually starts immediately and no waiting is needed
            if ($consultation->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Consultation is not in pending state to start the call.'
                ], 409);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid consultation type.'
            ], 422);
        }

        // تحديث الحالة (لو لزم الأمر)
        if ($consultation->status !== 'in_progress') {
            $consultation->status = 'in_progress';
            $consultation->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Call started for consultation.'
        ]);
    }
    public function endConsultation($id)
    {
        // Use patient->id instead of user id
        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient profile not found.'
            ], 404);
        }

        $consultation = Consultation::where('id', $id)
            ->where('patient_id', $patient->id)
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
                'message' => 'Consultation is not in a state to be completed.'
            ], 409);
        }

        $consultation->status = 'completed';
        $consultation->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Consultation marked as completed.'
        ]);
        
    }

}
