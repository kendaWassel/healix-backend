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
                'message' => __('messages.patient_not_found')
            ], 404);
        }

        // Get the latest medical record for the patient (if any)
        $record = $patient->medicalRecords()->with(['doctor.user', 'uploads'])->latest()->first();
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
        $careProvider = $user->careProvider;

        // One record per patient, edited in place by whichever authorized
        // provider (doctor, nurse, or physiotherapist) touches it — same
        // "find the patient's own latest record, or start one" pattern
        // already used by updateOwnMedicalRecord()/updatePregnancyInfo()
        // below. The previous version matched on
        // ['patient_id', 'doctor_id'], so a second doctor (or any care
        // provider, whose doctor_id is always null) editing the same
        // patient created a NEW row instead of updating the existing one.
        $medicalRecord = $patient->medicalRecords()->latest('id')->first()
            ?? new MedicalRecord(['patient_id' => $patientId]);

        // True partial update: only touch a field the caller actually sent.
        // The validation rules below don't all use `sometimes`, so
        // $validated includes an absent field as null — filling
        // unconditionally would wipe that field on the shared record every
        // time a different provider edits without resending it (e.g. a
        // nurse updating only previous_surgeries would null out the
        // doctor's diagnosis). Harmless when each provider had their own
        // row; destructive now that the record is shared.
        foreach ([
            'diagnosis', 'treatment_plan', 'current_medications', 'chronic_diseases',
            'pre_existing_conditions', 'other_conditions', 'previous_surgeries', 'allergies',
        ] as $field) {
            if ($request->has($field)) {
                $medicalRecord->{$field} = $validated[$field] ?? null;
            }
        }

        // Track whichever provider made this edit — overwrites the other
        // (a record edited by a doctor then a nurse shows the nurse as the
        // most recent editor), which is the same "last touched by" model
        // most single-shared-record systems use; UpdateMedicalRecordRequest
        // already guarantees exactly one of these two is non-null.
        if ($doctor) {
            $medicalRecord->doctor_id = $doctor->id;
        } elseif ($careProvider) {
            $medicalRecord->care_provider_id = $careProvider->id;
        }

        $medicalRecord->save();

        // Update attachments if provided (set medical_record_id on uploads)
        if (isset($validated['attachments_id'])) {
            // First, remove medical_record_id from any uploads that were previously attached to this record
            Upload::where('medical_record_id', $medicalRecord->id)->update(['medical_record_id' => null]);

            // Then, attach the new uploads to this medical record
            Upload::whereIn('id', $validated['attachments_id'])->update(['medical_record_id' => $medicalRecord->id]);
        }

        // Notify the patient regardless of which provider type made the
        // edit — previously gated on `if ($doctor)` only, so a care
        // provider's edit never notified the patient at all.
        if ($doctor || $careProvider) {
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
                'doctor_id' => $medicalRecord->doctor_id,
                'care_provider_id' => $medicalRecord->care_provider_id,
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
