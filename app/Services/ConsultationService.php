<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Doctor;
use App\Models\Consultation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Notifications\ConsultationRequestedNotification;
use App\Services\UltraMsgService;
use App\Services\TraccarSmsService;

class ConsultationService
{
    /**
     * Book a consultation
     *
     * @param array $validated
     * @return Consultation
     * @throws \Exception
     */
    public function bookConsultation(array $validated): Consultation
    {
        $doctor = Doctor::find($validated['doctor_id']);
        if (!$doctor) {
            throw new \Exception('Doctor not found', 404);
        }

        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Unauthenticated', 401);
        }

        $patient = $user->patient;
        if (!$patient) {
            throw new \Exception('Patient profile not found', 404);
        }

        if ($validated['call_type'] === 'schedule' && empty($validated['scheduled_at'])) {
            throw new \Exception('scheduled_at is required for schedule_later', 422);
        }

        // Validate booking time within doctor's available hours for schedule_later
        if (!empty($validated['scheduled_at'])) {
            $scheduled = Carbon::parse($validated['scheduled_at']);
            $time = $scheduled->format('H:i');
            if (!empty($doctor->from) && !empty($doctor->to)) {
                if (!($time >= $doctor->from && $time <= $doctor->to)) {
                    throw new \Exception('The scheduled time is outside the doctor\'s available hours', 422);
                }
            } else {
                throw new \Exception('Doctor availability hours are not set', 422);
            }
        }

        // Validate doctor's availability for call_now based on working hours
        if ($validated['call_type'] === 'call_now') {
            $now = Carbon::now('Asia/Damascus')->format('H:i');

            if (!empty($doctor->from) && !empty($doctor->to)) {
                if (!($now >= $doctor->from && $now <= $doctor->to)) {
                    throw new \Exception('Doctor is not available now', 409);
                }
            } else {
                throw new \Exception('Doctor availability hours are not set', 409);
            }

            $activeConsultation = Consultation::where('doctor_id', $doctor->id)
                ->where('status', 'in_progress')
                ->first();

            if ($activeConsultation) {
                throw new \Exception('Doctor is currently busy with another consultation.', 409);
            }
        }

        if (!empty($validated['scheduled_at'])) {
            $exists = Consultation::where('doctor_id', $doctor->id)
                ->where('scheduled_at', Carbon::parse($validated['scheduled_at']))
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($exists) {
                throw new \Exception('Requested time slot is already booked', 409);
            }
        }

        try {
            DB::beginTransaction();

            $consultation = Consultation::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'type' => $validated['call_type'],
                'status' => 'pending',
                'start_time' => $validated['call_type'] === 'call_now' ? Carbon::now() : null,
                'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at'], 'Asia/Damascus') : null,
            ]);

            DB::commit();

            // Load relationships for notification
            $consultation->load(['patient', 'doctor.user']);
            $doctor->loadMissing('user');

            // Get patient user for notification
            $patientUser = $consultation->patient;
            if (method_exists($patient, 'user') && $patient->user) {
                $patientUser = $patient->user;
            }

            // Notify doctor
            $doctor->user->notify(
                new ConsultationRequestedNotification($consultation, $patientUser, $doctor->user)
            );

            // Send WhatsApp message to doctor
            $ultraMsgService = new UltraMsgService();
            $result = $ultraMsgService->sendWhatsAppMessage($doctor->user->phone, "Hello {$doctor->user->full_name}, You have a new consultation booked.");
            if (!$result) {
                Log::warning('Failed to send WhatsApp notification', [
                    'doctor_id' => $doctor->id,
                    'phone' => $doctor->user->phone,
                ]);
            }
            
            // Send SMS message to doctor
            if ($doctor->user && $doctor->user->phone) {
                try {
                    $patientName = $patientUser->full_name ?? $patientUser->name ?? 'Unknown Patient';
                    $consultationType = $consultation->type === 'call_now' ? 'Call Now' : 'Scheduled';
                    $scheduledTime = $consultation->scheduled_at 
                        ? $consultation->scheduled_at->format('Y-m-d H:i') 
                        : 'Immediately';    

                    $smsMessage = "Hello {$doctor->user->full_name}\nYou have a new consultation booked.\nPatient Name: {$patientName}\nType: {$consultationType}\nTime: {$scheduledTime}";

                    $traccarSmsService = new TraccarSmsService();
                    $result = $traccarSmsService->sendSms($doctor->user->phone, $smsMessage);
                    
                    if (!$result) {
                        Log::warning('Failed to send SMS notification', [
                            'doctor_id' => $doctor->id,
                            'phone' => $doctor->user->phone,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Don't fail consultation creation if SMS fails
                    Log::error('Exception while sending SMS message', [
                        'doctor_id' => $doctor->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $consultation;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Start a consultation
     *
     * @param int $id
     * @param string|null $role
     * @return array
     * @throws \Exception
     */
    public function startConsultation(int $id, ?string $role = null): array
    {
        $user = Auth::user();
        $doctor = $user->doctor;
        $patient = $user->patient;

        if (!$doctor && !$patient) {
            throw new \Exception('Unauthorized.', 403);
        }

        // Fetch consultation based on role
        $consultation = Consultation::where('id', $id)
            ->when($doctor, fn($q) => $q->where('doctor_id', $doctor->id))
            ->when($patient, fn($q) => $q->where('patient_id', $patient->id))
            ->first();

        if (!$consultation) {
            throw new \Exception('Consultation not found.', 404);
        }

        // If already started → user just joins
        if ($consultation->status === 'in_progress') {
            return [
                'is_joining' => true,
                'consultation' => $consultation,
                'role' => $doctor ? 'doctor' : 'patient',
            ];
        }

        // Pending → this user is the first to start
        // Allowed types are 'schedule' and 'call_now'
        if (!in_array($consultation->type, ['schedule', 'call_now'])) {
            throw new \Exception('Invalid consultation type.', 422);
        }

        // Only pending consultations can be started
        if ($consultation->status !== 'pending') {
            throw new \Exception('Consultation is not in a state to be started.', 409);
        }

        // If it's a scheduled consultation, ensure scheduled_at has arrived
        if ($consultation->type === 'schedule') {
            if (empty($consultation->scheduled_at)) {
                throw new \Exception('Scheduled time is missing for this consultation.', 422);
            }

            $now = Carbon::now('Asia/Damascus');
            $scheduled = Carbon::parse($consultation->scheduled_at)->setTimezone('Asia/Damascus');
            if ($now->lt($scheduled) || $now->lte($scheduled)) {
                throw new \Exception('It is not time to start the scheduled consultation yet.', 409);
            }
        }

        // Start consultation
        $consultation->update([
            'status' => 'in_progress',
        ]);

        return [
            'consultation' => $consultation,
            'role' => $doctor ? 'doctor' : 'patient',
        ];
    }

    /**
     * End a consultation
     *
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function endConsultation(int $id): array
    {
        $user = Auth::user();
        $doctor = $user->doctor;
        $patient = $user->patient;

        if (!$doctor && !$patient) {
            throw new \Exception('Unauthorized.', 403);
        }

        // Fetch consultation based on role
        $consultation = Consultation::where('id', $id)
            ->when($doctor, fn($q) => $q->where('doctor_id', $doctor->id))
            ->when($patient, fn($q) => $q->where('patient_id', $patient->id))
            ->first();

        if (!$consultation) {
            throw new \Exception('Consultation not found or not authorized.', 404);
        }

        if ($consultation->status !== 'in_progress') {
            throw new \Exception('Consultation is not in progress.', 409);
        }

        // End the consultation
        $consultation->status = 'completed';
        $consultation->save();

        $currentRole = $doctor ? 'doctor' : 'patient';

        return [
            'consultation' => $consultation,
            'ended_by' => $currentRole,
        ];
    }
}












