<?php

namespace App\Console\Commands;

use App\Models\Consultation;
use App\Models\User;
use App\Notifications\ConsultationReminderNotification;
use App\Services\UltraMsgService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendConsultationReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consultations:send-reminders 
                            {--minutes=15 : Minutes before consultation to send reminder}
                            {--window=15 : Time window in minutes to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications to patients and doctors for upcoming consultations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $reminderMinutes = (int) $this->option('minutes');
        $windowMinutes = (int) $this->option('window');
        
        $now = Carbon::now('GMT+2:00');
        $reminderTime = $now->copy()->addMinutes($reminderMinutes);
        $windowEnd = $reminderTime->copy()->addMinutes($windowMinutes);

        $this->info("Checking for consultations between {$reminderTime->format('Y-m-d H:i:s')} and {$windowEnd->format('Y-m-d H:i:s')}");

        // Get consultations that are scheduled within the reminder window
        // Only get scheduled consultations (not call_now) that haven't been completed or cancelled
        $consultations = Consultation::with(['patient', 'doctor.user'])
            ->whereNotNull('scheduled_at')
            ->where('type', 'schedule')
            ->whereIn('status', ['scheduled', 'pending'])
            ->whereBetween('scheduled_at', [$reminderTime, $windowEnd])
            ->get();

        if ($consultations->isEmpty()) {
            $this->info('No consultations found in the reminder window.');
            return 0;
        }

        $this->info("Found {$consultations->count()} consultation(s) to send reminders for.");

        $sentCount = 0;

        foreach ($consultations as $consultation) {
            try {
                // Load relationships if not already loaded
                if (!$consultation->relationLoaded('patient')) {
                    $consultation->load('patient');
                }
                if (!$consultation->relationLoaded('doctor')) {
                    $consultation->load('doctor.user');
                }

                $patient = $consultation->patient;
                $doctor = $consultation->doctor;

                if (!$patient || !$doctor || !$doctor->user) {
                    $this->warn("Skipping consultation #{$consultation->id} - missing patient or doctor data");
                    continue;
                }

                // Get patient user
                $patientUser = $patient->user;
                if (!$patientUser) {
                    $this->warn("Skipping consultation #{$consultation->id} - patient user not found");
                    continue;
                }

                // Check if reminder was already sent to either patient or doctor
                $patientReminderSent = DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $patientUser->id)
                    ->where('type', ConsultationReminderNotification::class)
                    ->whereRaw('JSON_EXTRACT(data, "$.consultation_id") = ?', [$consultation->id])
                    ->exists();

                $doctorReminderSent = DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $doctor->user->id)
                    ->where('type', ConsultationReminderNotification::class)
                    ->whereRaw('JSON_EXTRACT(data, "$.consultation_id") = ?', [$consultation->id])
                    ->exists();

                if ($patientReminderSent && $doctorReminderSent) {
                    $this->warn("Reminder already sent for consultation #{$consultation->id}");
                    continue;
                }

                $scheduledTime = $consultation->scheduled_at 
                    ? $consultation->scheduled_at->format('Y-m-d H:i') 
                    : 'now';

                // Send email and database reminder to patient
                if (!$patientReminderSent) {
                    $patientUser->notify(
                        new ConsultationReminderNotification($consultation, 'patient', $doctor->user)
                    );
                    
                    // Send WhatsApp to patient
                    if ($patientUser->phone) {
                        $this->sendWhatsAppReminder(
                            $patientUser->phone,
                            $patientUser->full_name ?? 'Patient',
                            $doctor->user->full_name ?? 'Doctor',
                            $scheduledTime,
                            'patient'
                        );
                    }
                }

                // Send email and database reminder to doctor
                if (!$doctorReminderSent) {
                    $doctor->user->notify(
                        new ConsultationReminderNotification($consultation, 'doctor', $patientUser)
                    );
                    
                    // Send WhatsApp to doctor
                    if ($doctor->user->phone) {
                        $this->sendWhatsAppReminder(
                            $doctor->user->phone,
                            $doctor->user->full_name ?? 'Doctor',
                            $patientUser->full_name ?? 'Patient',
                            $scheduledTime,
                            'doctor'
                        );
                    }
                }

                $sentCount++;
                $this->info("Sent reminders (Email + WhatsApp) for consultation #{$consultation->id} (Patient: {$patientUser->full_name}, Doctor: {$doctor->user->full_name})");

            } catch (\Exception $e) {
                $this->error("Failed to send reminder for consultation #{$consultation->id}: " . $e->getMessage());
            }
        }

        $this->info("Successfully sent {$sentCount} reminder(s).");
        return 0;
    }

    /**
     * Send WhatsApp reminder message
     */
    private function sendWhatsAppReminder(string $phone, string $recipientName, string $otherPartyName, string $scheduledTime, string $recipientType): void
    {
        try {
            $ultraMsgService = new UltraMsgService();
            
            if (!$ultraMsgService->isConfigured()) {
                $this->warn("UltraMsg not configured. Skipping WhatsApp reminder to {$recipientName}");
                return;
            }

            if ($recipientType === 'patient') {
                $message = "🔔 Consultation Reminder\n\n";
                $message .= "Hello {$recipientName},\n\n";
                $message .= "You have a consultation scheduled with Dr. {$otherPartyName} in 15 minutes.\n\n";
                $message .= "Scheduled Time: {$scheduledTime}\n\n";
                $message .= "Please be ready for your consultation.";
            } else {
                $message = "🔔 Consultation Reminder\n\n";
                $message .= "Hello Dr. {$recipientName},\n\n";
                $message .= "You have a consultation scheduled with {$otherPartyName} in 15 minutes.\n\n";
                $message .= "Scheduled Time: {$scheduledTime}\n\n";
                $message .= "Please be ready for your consultation.";
            }

            $result = $ultraMsgService->sendWhatsAppMessage($phone, $message);
            
            if ($result['success']) {
                $this->info("WhatsApp reminder sent to {$recipientName} ({$phone})");
            } else {
                $this->warn("Failed to send WhatsApp reminder to {$recipientName}: {$result['message']}");
                Log::warning('Failed to send WhatsApp reminder', [
                    'phone' => $phone,
                    'recipient' => $recipientName,
                    'error' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            $this->error("Exception while sending WhatsApp reminder to {$recipientName}: " . $e->getMessage());
            Log::error('Exception while sending WhatsApp reminder', [
                'phone' => $phone,
                'recipient' => $recipientName,
                'error' => $e->getMessage()
            ]);
        }
    }
}

