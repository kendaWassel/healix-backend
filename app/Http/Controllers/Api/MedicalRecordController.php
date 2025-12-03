<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
use App\Models\MedicalRecord;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
        public function viewDetails($patientId){

        $doctor = Auth::user()->doctor;
        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access - only doctors can view medical records.'
            ], 403);
        }


        $patient = Patient::with(['user', 'medicalRecords'])->find($patientId);
            if (!$patient) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Patient not found.'
                ], 404);
            }
        // return the latest medical record for the patient (if any)
        $record = $patient->medicalRecords()->with('attachments')->latest()->first();
        dd($record);

        $medicalRecordData = null;
        if ($record) {
            $attachments = $record->attachments()->get();
            $medicalRecordData = [
                'id' => $record->id,
                'doctor_id' => $record->doctor_id,
                'diagnosis' => $record->diagnosis,
                'treatment_plan' => $record->treatment_plan,
                'chronic_diseases' => $record->chronic_diseases,
                'previous_surgeries' => $record->previous_surgeries,
                'allergies' => $record->allergies,
                'current_medications' => $record->current_medications,
                'attachments' => $attachments->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'file_name' => basename($file->file_path),
                        'file_url' => asset('storage/' . ltrim($file->file_path, '/')),
                    ];
                })->values(),
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
    public function updateMedicalRecord(Request $request, $patientId)
    {
        $doctor = Auth::user()->doctor;
        if (!$doctor) {
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
            'chronic_diseases' => 'nullable|string',
            'previous_surgeries' => 'nullable|string',
            'allergies' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'attachments_id' => 'nullable|array',
            'attachments_id.*' => 'integer|exists:uploads,id',
        ]);

        $record = MedicalRecord::updateOrCreate(
            ['patient_id' => $patient->id, 'doctor_id' => $doctor->id],
            [
                'diagnosis' => $validated['diagnosis'] ?? null,
                'treatment_plan' => $validated['treatment_plan'] ?? null,
                'chronic_diseases' => $validated['chronic_diseases'] ?? null,
                'previous_surgeries' => $validated['previous_surgeries'] ?? null,
                'allergies' => $validated['allergies'] ?? null,
                'current_medications' => $validated['current_medications'] ?? null,
                'attachments_id' => $validated['attachments_id'] ?? null,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Medical record updated successfully.',
            'data' => [
                'medical_record_id' => $record->id,
                'patient_id' => $record->patient_id,
                'doctor_id' => $record->doctor_id,
            ]
        ], 200);
    }
}