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
        $user = Auth::user();

        if (!$user) {
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
            'diagnosis' => 'required|string',
            'treatment_plan' => 'required|string',
            'current_medications' => 'required|string',
        ]);
        $doctor=Doctor::where('user_id', $user->id)->first();
        $medicalRecord = MedicalRecord::where('patient_id', $patientId)
            ->first();

        if (!$medicalRecord) {
            return response()->json([
                'status' => 'error',
                'message' => 'Medical record not found for this patient and doctor.'
            ], 404);
        }

        
        $medicalRecord->update([
            'diagnosis' => $validated['diagnosis'],
            'treatment_plan' => $validated['treatment_plan'],
            'current_medications' => $validated['current_medications'],
            'doctor_id' => $doctor->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Medical record updated successfully.',
            'data' => [
                'medical_record_id' => $medicalRecord->id,
                'patient_id' => $medicalRecord->patient_id,
                'doctor_id' => $doctor->id,
                'diagnosis' => $medicalRecord->diagnosis,
                'treatment_plan' => $medicalRecord->treatment_plan,
                'current_medications' => $medicalRecord->current_medications
            ]
        ], 200);
    }

}