<?php

namespace App\Listeners;

use App\Events\ConsultationBooked;
use App\Notifications\ConsultationRequestedNotification;
use App\Services\UltraMsgService;
use App\Models\User;
use Illuminate\Support\Traits\Localizable;

class SendConsultationNotification
{
    use Localizable;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle ConsultationBooked event.
     */
    public function handleConsultationBooked(ConsultationBooked $event): void
    {
        // Get patient name
        $patientName = $this->getPatientName($event->patient);
        $scheduledAt = $event->consultation->scheduled_at
            ? $event->consultation->scheduled_at->format('Y-m-d H:i')
            : __('notification.time_now');

        // Get doctor user (event->doctor is already a User model in ConsultationBooked)
        $doctorUser = $event->doctor;

        // Rendered in the doctor's own preferred_locale (see ConsultationService
        // for the same mechanism, applied to the other WhatsApp/SMS send site).
        $message = ($doctorUser instanceof User)
            ? $this->withLocale($doctorUser->preferredLocale(), fn () => __('notification.wa_booked_short', [
                'name' => $patientName,
                'time' => $scheduledAt,
            ]))
            : __('notification.wa_booked_short', ['name' => $patientName, 'time' => $scheduledAt]);
        
        if ($doctorUser && $doctorUser instanceof User && $doctorUser->phone) {
            try {
                // Send WhatsApp message
                $ultraMsgService = new UltraMsgService();
                $result = $ultraMsgService->sendWhatsAppMessage($doctorUser->phone, $message);
                
                if (!$result['success']) {
                    \Illuminate\Support\Facades\Log::warning('Failed to send WhatsApp notification via listener', [
                        'doctor_id' => $doctorUser->id,
                        'phone' => $doctorUser->phone,
                        'error' => $result['message']
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Exception while sending WhatsApp message via listener', [
                    'doctor_id' => $doctorUser->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Send notification (includes SMS via custom channel)
            $doctorUser->notify(new ConsultationRequestedNotification(
                $event->consultation, 
                $event->patient,
                $doctorUser
            ));
        }
    }

    /**
     * Get patient name from various model types.
     */
    protected function getPatientName($patient): string
    {
        if ($patient instanceof User) {
            return $patient->full_name ?? $patient->name ?? 'Unknown';
        }
        
        if (method_exists($patient, 'user') && $patient->user) {
            return $patient->user->full_name ?? $patient->user->name ?? 'Unknown';
        }
        
        if (isset($patient->full_name)) {
            return $patient->full_name;
        }
        
        if (isset($patient->name)) {
            return $patient->name;
        }
        
        return 'Unknown';
    }
}
