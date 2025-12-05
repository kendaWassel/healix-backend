<?php

namespace App\Console\Commands;

use App\Models\Consultation;
use App\Models\User;
use App\Notifications\ConsultationArrivedNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendConsultationArrivalNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consultations:send-arrival-notifications 
                            {--window=5 : Time window in minutes to check for arriving consultations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send arrival notifications to patients and doctors when consultation appointment time arrives';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $windowMinutes = (int) $this->option('window');
        
        $now = Carbon::now();
        $windowStart = $now->copy()->subMinutes($windowMinutes);
        $windowEnd = $now->copy()->addMinutes($windowMinutes);

        $this->info("Checking for consultations arriving between {$windowStart->format('Y-m-d H:i:s')} and {$windowEnd->format('Y-m-d H:i:s')}");

        // Get consultations that are scheduled within the arrival window
        // Only get scheduled consultations (not call_now) that haven't been completed or cancelled
        $consultations = Consultation::with(['patient', 'doctor.user'])
            ->whereNotNull('scheduled_at')
            ->where('type', 'schedule')
            ->whereIn('status', ['scheduled', 'pending'])
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->get();

        if ($consultations->isEmpty()) {
            $this->info('No consultations found in the arrival window.');
            return 0;
        }

        $this->info("Found {$consultations->count()} consultation(s) to send arrival notifications for.");

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

                // Check if arrival notification was already sent to either patient or doctor
                $patientNotificationSent = DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $patient->id)
                    ->where('type', ConsultationArrivedNotification::class)
                    ->whereRaw('JSON_EXTRACT(data, "$.consultation_id") = ?', [$consultation->id])
                    ->exists();

                $doctorNotificationSent = DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $doctor->user->id)
                    ->where('type', ConsultationArrivedNotification::class)
                    ->whereRaw('JSON_EXTRACT(data, "$.consultation_id") = ?', [$consultation->id])
                    ->exists();

                if ($patientNotificationSent && $doctorNotificationSent) {
                    $this->warn("Arrival notification already sent for consultation #{$consultation->id}");
                    continue;
                }

                // Send arrival notification to patient
                if (!$patientNotificationSent) {
                    $patient->notify(
                        new ConsultationArrivedNotification($consultation, 'patient', $doctor->user)
                    );
                }

                // Send arrival notification to doctor
                if (!$doctorNotificationSent) {
                    $doctor->user->notify(
                        new ConsultationArrivedNotification($consultation, 'doctor', $patient)
                    );
                }

                $sentCount++;
                $this->info("Sent arrival notifications for consultation #{$consultation->id} (Patient: {$patient->full_name}, Doctor: {$doctor->user->full_name})");

            } catch (\Exception $e) {
                $this->error("Failed to send arrival notification for consultation #{$consultation->id}: " . $e->getMessage());
            }
        }

        $this->info("Successfully sent {$sentCount} arrival notification(s).");
        return 0;
    }
}

