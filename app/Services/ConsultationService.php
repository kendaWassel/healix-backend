<?php

namespace App\Services;

use App\Models\Consultation;
use App\Models\Doctor;
use App\Notifications\ConsultationRequestedNotification;
use App\Services\GoogleMeetService;
use App\Services\TraccarSmsService;
use App\Services\UltraMsgService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsultationService
{
    protected GoogleMeetService $googleMeetService;

    public function __construct(GoogleMeetService $googleMeetService)
    {
        $this->googleMeetService = $googleMeetService;
    }
    public function bookConsultation(array $validated): Consultation
    {
        $doctor = Doctor::find($validated['doctor_id']);
        if (!$doctor) {
            throw new \Exception(__('messages.doctor_not_found'), 404);
        }
        

        $user = Auth::user();
        if (!$user) {
            throw new \Exception(__('auth.unauthenticated'), 401);
        }

        $patient = $user->patient;
        if (!$patient) {
            throw new \Exception(__('messages.patient_profile_not_found'), 404);
        }

        if ($validated['call_type'] === 'schedule' && empty($validated['scheduled_at'])) {
            throw new \Exception(__('consultation.scheduled_at_required'), 422);
        }

        // Validate booking time within doctor's available hours for schedule
        if (!empty($validated['scheduled_at'])) {
            $scheduled = Carbon::parse($validated['scheduled_at']);
            $time = $scheduled->format('H:i');
            if (!empty($doctor->from) && !empty($doctor->to)) {
                if (!($time >= $doctor->from && $time <= $doctor->to)) {
                    throw new \Exception(__('consultation.scheduled_outside_hours'), 422);
                }
            } else {
                throw new \Exception(__('consultation.doctor_hours_not_set'), 422);
            }
        }

        // Validate doctor's availability for call_now based on working hours
        if ($validated['call_type'] === 'call_now') {
            $now = Carbon::now()->format('H:i');

            if (!empty($doctor->from) && !empty($doctor->to)) {
                if (!($now >= $doctor->from && $now <= $doctor->to)) {
                    throw new \Exception(__('consultation.doctor_unavailable'), 409);
                }
            } else {
                throw new \Exception(__('consultation.doctor_hours_not_set'), 409);
            }

            $activeConsultation = Consultation::where('doctor_id', $doctor->id)
                ->where('status', 'in_progress')
                ->first();

            if ($activeConsultation) {
                throw new \Exception(__('consultation.doctor_busy'), 409);
            }
        }

        if (!empty($validated['scheduled_at'])) {
            $exists = Consultation::where('doctor_id', $doctor->id)
                ->where('scheduled_at', Carbon::parse($validated['scheduled_at']))
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($exists) {
                throw new \Exception(__('consultation.slot_taken'), 409);
            }
        }

        try {
            DB::beginTransaction();

            $startTime = $validated['call_type'] === 'call_now'
                ? Carbon::now()
                : Carbon::parse($validated['scheduled_at']);

            $consultation = Consultation::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'type' => $validated['call_type'],
                'status' => 'pending',
                'start_time' => $validated['call_type'] === 'call_now' ? Carbon::now() : null,
                // For call_now, set scheduled_at to now; for schedule, use provided datetime
                'scheduled_at' => $validated['call_type'] === 'call_now'
                    ? Carbon::now()
                    : (!empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null),
            ]);
            // Generate Google Meet link for the consultation
            $doctor->loadMissing('user');
            if ($doctor->user && !empty($doctor->user->email)) {
                try {
                    $meetDetails = $this->googleMeetService->createMeetEvent(
                        $doctor->user->email,
                        $startTime,
                        30, // default consultation duration in minutes
                        $consultation->id
                    );

                    if ($meetDetails) {
                        $consultation->update([
                            'google_meet_link' => $meetDetails['meet_link'],
                            'google_calendar_event_id' => $meetDetails['event_id'],
                        ]);
                    }
                } catch (\Exception $e) {
                    // Don't fail consultation creation if Google Meet generation fails
                    Log::error('Exception while creating Google Meet event', [
                        'doctor_id' => $doctor->id,
                        'consultation_id' => $consultation->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

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

            $meetLinkText = $consultation->google_meet_link ? "\nGoogle Meet Link: {$consultation->google_meet_link}" : '';

            // Send WhatsApp message to doctor
            $ultraMsgService = new UltraMsgService();
            $whatsAppMessage = "Hello {$doctor->user->full_name}, You have a new consultation booked.{$meetLinkText}";
            $result = $ultraMsgService->sendWhatsAppMessage($doctor->user->phone, $whatsAppMessage);
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

                    $smsMessage = "Hello {$doctor->user->full_name}\nYou have a new consultation booked.\nPatient Name: {$patientName}\nType: {$consultationType}\nTime: {$scheduledTime}{$meetLinkText}";

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

    public function startConsultation(int $id, ?string $role = null): array
    {
        $user = Auth::user();
        $doctor = $user->doctor;
        $patient = $user->patient;

        if (!$doctor && !$patient) {
            throw new \Exception(__('messages.unauthorized'), 403);
        }

        // Fetch consultation based on role
        $consultation = Consultation::where('id', $id)
            ->when($doctor, fn($q) => $q->where('doctor_id', $doctor->id))
            ->when($patient, fn($q) => $q->where('patient_id', $patient->id))
            ->first();

        if (!$consultation) {
            throw new \Exception(__('consultation.not_found'), 404);
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
            throw new \Exception(__('consultation.invalid_type'), 422);
        }

        // Only pending consultations can be started
        if ($consultation->status !== 'pending') {
            throw new \Exception(__('consultation.cannot_start'), 409);
        }

        // If it's a scheduled consultation, ensure scheduled_at has arrived
        if ($consultation->type === 'schedule') {
            if (empty($consultation->scheduled_at)) {
                throw new \Exception(__('consultation.scheduled_time_missing'), 422);
            }

            $now = Carbon::now();
            $scheduled = Carbon::parse($consultation->scheduled_at);
            if ($now->lt($scheduled) || $now->lte($scheduled)) {
                throw new \Exception(__('consultation.too_early'), 409);
            }
        }

        // Start consultation
        $consultation->update([
            'status' => 'in_progress',
        ]);

        return [
            'is_joining' => false,
            'consultation' => $consultation,
            'role' => $doctor ? 'doctor' : 'patient',
        ];
    }

    public function endConsultation(int $id): array
    {
        $user = Auth::user();
        $doctor = $user->doctor;
        $patient = $user->patient;

        if (!$doctor && !$patient) {
            throw new \Exception(__('messages.unauthorized'), 403);
        }

        // Fetch consultation based on role
        $consultation = Consultation::where('id', $id)
            ->when($doctor, fn($q) => $q->where('doctor_id', $doctor->id))
            ->when($patient, fn($q) => $q->where('patient_id', $patient->id))
            ->first();

        if (!$consultation) {
            throw new \Exception(__('consultation.not_found_or_unauthorized'), 404);
        }

        if ($consultation->status !== 'in_progress') {
            throw new \Exception(__('consultation.not_in_progress'), 409);
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












