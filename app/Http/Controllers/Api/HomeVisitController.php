<?php

namespace App\Http\Controllers\Api;

use App\Models\HomeVisit;
use App\Models\Consultation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HomeVisitController extends Controller
{
    public function requestHomeVisit(Request $request)
    {
        $validated = $request->validate([
            'consultation_id' => 'required|exists:consultations,id',
            'patient_id' => 'required|exists:patients,id',
            'service_type' => 'required|in:nurse,physiotherapist',
            'reason' => 'nullable|string|max:255',
            'scheduled_at' => 'required|date_format:H:i',
        ]);

        $doctor = auth()->user()->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not authorized.'
            ], 403);
        }

        // Ensure consultation belongs to the doctor
        $consultation = Consultation::where('id', $validated['consultation_id'])
            ->where('doctor_id', $doctor->id)
            ->where('patient_id', $validated['patient_id'])
            ->first();


        if (!$consultation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Consultation does not belong to this doctor.'
            ], 403);
        }

        $homeVisit = HomeVisit::create([
            'consultation_id' => $validated['consultation_id'],
            'patient_id' => $validated['patient_id'],
            'doctor_id' => $doctor->id,
            'service_type' => $validated['service_type'],
            'reason' => $validated['reason'],
            'scheduled_at' => $validated['scheduled_at'],
            'status' => 'pending'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Home visit request created successfully.',
            'data' => $homeVisit
        ]);
    }

    public function reRequestHomeVisit(Request $request, $visitId)
    {
        $validated = $request->validate([
            'scheduled_at' => 'required',
        ]);

        $user = auth()->user();
        $patient = \App\Models\Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient not found.'
            ], 404);
        }

        $oldVisit = HomeVisit::where('id', $visitId)
            ->where('patient_id', $patient->id)
            ->first();

        if (!$oldVisit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Home visit not found.'
            ], 404);
        }

        if ($oldVisit->status !== 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only cancelled home visits can be re-requested.'
            ], 400);
        }

        $newVisit = HomeVisit::updateOrCreate([
            'consultation_id' => $oldVisit->consultation_id,
            'patient_id' => $patient->id,
            'doctor_id' => $oldVisit->doctor_id,
            'service_type' => $oldVisit->service_type,
            'reason' => $oldVisit->reason,
            'scheduled_at' => $validated['scheduled_at'],
            'status' => 'pending',
            'address' => $oldVisit->address

        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Home visit re-requested successfully.',
            'data' => $newVisit
        ]);
    }
}
