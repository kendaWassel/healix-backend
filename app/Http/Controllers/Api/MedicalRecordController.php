<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Http\Resources\MedicalRecordResource;

class MedicalRecordController extends Controller
{
 
    /**
     * Doctor, Nurse, Physiotherapist: View patient medical record details.
     */
    public function viewDetails($patientId)
    {
        $user = Auth::user();
        $doctor = $user->doctor;
        $care_provider = $user->careProvider;
        $isAuthorized = $doctor || ($care_provider && in_array($care_provider->type, ['nurse', 'physiotherapist']));
        if (!$user || !$isAuthorized) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized - only doctors, nurses, and physiotherapists can view patient medical records.'
            ], 403);
        }

        $patient = Patient::with(['user', 'medicalRecords.doctor.user'])->find($patientId);
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient not found.'
            ], 404);
        }

        // Get the latest medical record for the patient (if any)
        $record = $patient->medicalRecords()->with(['doctor.user', 'attachments'])->latest()->first();
        
        $medicalRecordData = null;
        if ($record) {
            $attachments = $record->attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'file_name' => basename($attachment->file_path),
                    'file_url' => asset('storage/' . ltrim($attachment->file_path, '/')),
                ];
            });
            
            $medicalRecordData = [
                'id' => $record->id,
                'doctor_id' => $record->doctor_id,
                'care_provider_id' => $care_provider ? $care_provider->id : null,
                'doctor_name' => $record->doctor?->user ? 'Dr. ' . $record->doctor->user->full_name : null,
                'diagnosis' => $record->diagnosis,
                'treatment_plan' => $record->treatment_plan,
                'chronic_diseases' => $record->chronic_diseases,
                'previous_surgeries' => $record->previous_surgeries,
                'allergies' => $record->allergies,
                'current_medications' => $record->current_medications,
                'attachments' => $attachments,
                'created_at' => $record->created_at?->toDateTimeString(),
                'updated_at' => $record->updated_at?->toDateTimeString(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'patient_id' => $patient->id,
                'patient_name' => $patient->user->full_name,
                'gender' => $patient->gender,
                'birth_date' => $patient->birth_date,
                'medical_record' => $medicalRecordData,
            ]
        ], 200);
    }

    /**
     * Doctor , Nurse, Physiotherapist: Create or update a patient's medical record.
     * 
     * Endpoint: PUT /api/medical-records/{patientId}
     */
    public function updateMedicalRecord(UpdateMedicalRecordRequest $request, $patientId)
    {
        $validated = $request->validated();

        $patient = Patient::find($patientId);
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient not found.'
            ], 404);
        }

        $user = Auth::user();
        $doctor = $user->doctor;
        $care_provider = $user->careProvider;
        
        // Use updateOrCreate to handle both create and update
        $medicalRecord = MedicalRecord::updateOrCreate(
            [
                'patient_id' => $patientId,
                'doctor_id' => $doctor ? $doctor->id : null,
            ],
            [
                'diagnosis' => $validated['diagnosis'] ?? null,
                'treatment_plan' => $validated['treatment_plan'] ?? null,
                'current_medications' => $validated['current_medications'] ?? null,
                'chronic_diseases' => $validated['chronic_diseases'] ?? null,
                'previous_surgeries' => $validated['previous_surgeries'] ?? null,
                'allergies' => $validated['allergies'] ?? null,
            ]
        );

        // Sync attachments if provided
        if (isset($validated['attachments_id'])) {
            $medicalRecord->attachments()->sync($validated['attachments_id']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Medical record updated successfully.',
            'data' => [
                'medical_record_id' => $medicalRecord->id,
                'patient_id' => $medicalRecord->patient_id,
                'doctor_id' => $doctor ? $doctor->id : null,
                'diagnosis' => $medicalRecord->diagnosis,
                'treatment_plan' => $medicalRecord->treatment_plan,
                'current_medications' => $medicalRecord->current_medications,
            ]
        ], 200);
    }
    
    /**
     * Patient: View their own medical record.
     * 
     * Endpoint: GET /api/patient/medical-record
     */
    public function getPatientMedicalRecord()
    {
        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient not found for this user.'
            ], 404);
        }

        $record = $patient->medicalRecords()->with(['doctor.user', 'attachments'])->latest()->first();

        if (!$record) {
            return response()->json([
                'status' => 'empty',
                'message' => 'No medical record found.',
                'data' => null
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Medical record retrieved successfully.',
            'data' => 
                new MedicalRecordResource($record)
        ], 200);
    }

}