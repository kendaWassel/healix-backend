<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
use App\Models\MedicalRecord;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Http\Requests\UpdatePatientMedicalRecordRequest;
use App\Http\Requests\UpdatePregnancyInfoRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Notifications\MedicalReportAddedNotification;
use Illuminate\Support\Facades\Storage;
use App\Models\Upload;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MedicalRecordController extends Controller
{
    use AuthorizesRequests;


    /**
     * Doctor, Nurse, Physiotherapist: View patient medical record details.
     */
    public function viewDetails($patientId)
    {
        $patient = Patient::with(['user', 'medicalRecords.doctor.user'])->find($patientId);
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.patient_not_found')
            ], 404);
        }

        // Get the latest medical record for the patient (if any)
        $record = $patient->medicalRecords()->with(['doctor.user', 'uploads'])->latest()->first();

        // Authorize against the real record when one exists; otherwise
        // against an unsaved stand-in carrying just patient_id, so a patient
        // with no record yet still gets the same admin/own-patient/
        // doctor-with-a-consultation check rather than an unguarded read.
        $this->authorize('view', $record ?? new MedicalRecord(['patient_id' => $patient->id]));

        $medicalRecordData = null;
        if ($record) {
            $medicalRecordData = new MedicalRecordResource($record);
        }
        $gender = $patient->gender === 'male' ? __('personal.gender_male') : ($patient->gender === 'female' ? __('personal.gender_female') : __('personal.gender_other'));

        return response()->json([
            'status' => 'success',
            'data' => [
                'patient_id' => $patient->id,
                'patient_name' => $patient->user->full_name,
                'gender' => $gender,
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

        // No medical_record_id (e.g. an upload mid-registration, not yet
        // attached to any record) means there's nothing to check a
        // relationship against — deny rather than guess.
        $medicalRecord = $upload->medicalRecord;
        if (!$medicalRecord) {
            abort(404);
        }
        $this->authorize('view', $medicalRecord);

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
                'message' => __('messages.patient_not_found')
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
                'other_conditions' => $validated['other_conditions'] ?? null,
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

        if ($doctor) {
            $patient->loadMissing('user');
            if ($patient->user) {
                $patient->user->notify(new MedicalReportAddedNotification($medicalRecord));
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('medical.record_updated'),
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
     * Patient: Update the self-reported fields of their own medical record
     * (chronic diseases, allergies, previous surgeries, current medications).
     *
     * Diagnosis and treatment plan are clinical judgment and stay
     * provider-only via updateMedicalRecord().
     *
     * Endpoint: PUT /api/patient/medical-record
     */
    public function updateOwnMedicalRecord(UpdatePatientMedicalRecordRequest $request)
    {
        $validated = $request->validated();
        $patient = $request->user()->patient;

        // Same get-or-create-latest pattern as updatePregnancyInfo, so both
        // self-service edits land on the same "current" record.
        $record = $patient->medicalRecords()->latest('id')->first()
            ?? new MedicalRecord(['patient_id' => $patient->id]);

        $record->fill($validated);
        $record->save();

        return response()->json([
            'status' => 'success',
            'message' => __('medical.record_updated'),
            'data' => new MedicalRecordResource($record->fresh(['doctor.user', 'uploads'])),
        ], 200);
    }

    /**
     * Patient: Update their own pregnancy information.
     *
     * Pregnancy is patient-reported, so it is kept separate from the clinical
     * fields that only providers may edit. It is written to the patient's most
     * recent medical record (a patient-owned one is created if none exists).
     *
     * Endpoint: PUT /api/patient/medical-record/pregnancy
     */
    public function updatePregnancyInfo(UpdatePregnancyInfoRequest $request)
    {
        $validated = $request->validated();
        $patient = $request->user()->patient;

        $isPregnant = (bool) $validated['is_pregnant'];

        // latest('id') is deterministic (created_at has only second granularity),
        // so the record written here is the same one verification reads back.
        $record = $patient->medicalRecords()->latest('id')->first()
            ?? new MedicalRecord(['patient_id' => $patient->id]);

        $record->is_pregnant = $isPregnant;
        $record->save();

        return response()->json([
            'status' => 'success',
            'message' => __('medical.pregnancy_updated'),
            'data' => [
                'medical_record_id' => $record->id,
                'is_pregnant' => $record->is_pregnant,
            ],
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
                'message' => __('messages.patient_not_found_for_user')
            ], 404);
        }
        $record = $patient->medicalRecords()->with(['doctor.user', 'uploads'])->latest('id')->first();
        

        if (!$record) {
            return response()->json([
                'status' => 'empty',
                'message' => __('medical.record_not_found'),
                'data' => null
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'message' => __('medical.record_retrieved'),
            'data' => 
                new MedicalRecordResource($record)
        ], 200);
    }

}
