<?php

namespace App\Http\Controllers\Api;

use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class MedicalRecordController extends Controller
{
 
    /**
     * Doctor: View patient medical record details.
     * 
     * Endpoint: GET /api/doctor/patients/{patient_id}/view-details
     */
    public function viewDetails($patientId)
    {
        $doctor = Auth::user()->doctor;
        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access - only doctors can view medical records.'
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
     * Doctor: Update or create patient medical record.
     * 
     * Endpoint: PUT /api/doctor/patients/{patient_id}/medical-record/update
     */
    public function updateMedicalRecord(Request $request, $patientId)
    {
        $user = Auth::user();

        if (!$user || !$user->doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized - only doctors can update medical records.'
            ], 403);
        }

        $patient = Patient::find($patientId);
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient not found.'
            ], 404);
        }

        $validated = $request->validate([
            'diagnosis' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'chronic_diseases' => 'nullable|string',
            'previous_surgeries' => 'nullable|string',
            'allergies' => 'nullable|string',
            'attachments_id' => 'nullable|array',
            'attachments_id.*' => 'integer|exists:uploads,id',
        ]);

        $doctor = $user->doctor;
        
        // Use updateOrCreate to handle both create and update
        $medicalRecord = MedicalRecord::updateOrCreate(
            [
                'patient_id' => $patientId,
                'doctor_id' => $doctor->id,
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
                'doctor_id' => $doctor->id,
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
    public function getPatientMedicalRecord(Request $request)
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

        $attachments = $record->attachments->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'file_name' => basename($attachment->file_path),
                'file_url' => asset('storage/' . ltrim($attachment->file_path, '/')),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $record->id,
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
            ]
        ], 200);
    }

}