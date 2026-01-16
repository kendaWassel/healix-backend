<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use Illuminate\Support\Facades\Storage;
use App\Models\Upload;
use App\Policies\MedicalRecordPolicy;

class MedicalRecordController extends Controller
{
 
    /**
     * Doctor, Nurse, Physiotherapist: View patient medical record details.
     */
    public function viewDetails($patientId)
    {
        $patient = Patient::with(['user', 'medicalRecords.doctor.user'])->find($patientId);
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient not found.'
            ], 404);
        }

        // Get the latest medical record for the patient (if any)
        $record = $patient->medicalRecords()->with(['doctor.user', 'uploads'])->latest()->first();
        $medicalRecordData = null;
        if ($record) {
            $medicalRecordData = new MedicalRecordResource($record);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'patient_id' => $patient->id,
                'patient_name' => $patient->user->full_name,
                'gender' => $patient->gender,
                'birth_date' => $patient->birth_date,
                'medical_record' => $medicalRecordData
            ]
        ], 200);
    }

    /**
     * Download a medical record attachment with authorization checks.
     */
    public function downloadAttachment($id)
    {
        $upload = Upload::findOrFail($id);

        $path = Storage::disk('public')->path($upload->file_path);
        if (!file_exists($path)) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $upload->mime,
        ];

        return response()->download($path, $upload->file, $headers);
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

        // Update attachments if provided (set medical_record_id on uploads)
        if (isset($validated['attachments_id'])) {
            // First, remove medical_record_id from any uploads that were previously attached to this record
            Upload::where('medical_record_id', $medicalRecord->id)->update(['medical_record_id' => null]);
            
            // Then, attach the new uploads to this medical record
            Upload::whereIn('id', $validated['attachments_id'])->update(['medical_record_id' => $medicalRecord->id]);
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
        $this->authorize('view', $patient);

        $record = $patient->medicalRecords()->with(['doctor.user', 'uploads'])->latest()->first();

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
