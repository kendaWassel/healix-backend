<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
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

        $this->authorize('create', HomeVisit::class);
        $doctor = auth()->user()->doctor;

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

    /**
     * POST /api/home-visits/{visit_id}/follow-up
     * Description: Allows a care provider to create a follow-up home visit for a completed session.
     */
    public function createFollowUpHomeVisit(Request $request, $visitId)
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date_format:Y-m-d H:i:s',
        ]);

        $this->authorize('create', \App\Models\HomeVisit::class);
        $careProvider = auth()->user()->careProvider;
        // Find the original home visit and ensure it belongs to the doctor and is completed
        $originalVisit = HomeVisit::where('id', $visitId)
            ->where('care_provider_id', $careProvider->id)
            ->where('status', 'completed')
            ->first();

        if (!$originalVisit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Original home visit not found or not eligible for follow-up.'
            ], 404);
        }

        // Create the follow-up visit
        $followUpVisit = HomeVisit::create([
            'consultation_id' => $originalVisit->consultation_id,
            'patient_id' => $originalVisit->patient_id,
            'doctor_id' => $careProvider->id,
            'service_type' => $originalVisit->service_type,
            'reason' => $originalVisit->reason ?: 'Follow-up session',
            'scheduled_at' => $validated['scheduled_at'],
            'status' => 'accepted', // Directly accepted since it's a follow-up
            'address' => $originalVisit->address, 

        ]);


        return response()->json([
            'status' => 'success',
            'message' => 'Follow-up home visit created successfully.',
            'data' => $followUpVisit
        ]);
    }
}
