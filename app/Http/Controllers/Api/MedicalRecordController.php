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
        $record = $patient->medicalRecords()->with(['doctor.user', 'uploads'])->latest()->first();
        
        $medicalRecordData = null;
        if ($record) {
            $images = [];
            $files = [];

            $record->uploads->each(function ($upload) use (&$images, &$files) {
                $uploadData = [
                    'id' => $upload->id,
                    'file_name' => basename($upload->file_path),
                    'file_url' => str_starts_with($upload->mime, 'image/')
                        ? asset('storage/' . ltrim($upload->file_path, '/'))
                        : route('medical-record.attachment.download', ['id' => $upload->id])
                        
                ];

                // Check if it's an image based on MIME type
                if (str_starts_with($upload->mime, 'image/')) {
                    $images[] = $uploadData;
                } else {
                    $files[] = $uploadData;
                }
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
                'images' => $images,
                'files' => $files,
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

        // Update attachments if provided (set medical_record_id on uploads)
        if (isset($validated['attachments_id'])) {
            // First, remove medical_record_id from any uploads that were previously attached to this record
            \App\Models\Upload::where('medical_record_id', $medicalRecord->id)->update(['medical_record_id' => null]);
            
            // Then, attach the new uploads to this medical record
            \App\Models\Upload::whereIn('id', $validated['attachments_id'])->update(['medical_record_id' => $medicalRecord->id]);
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