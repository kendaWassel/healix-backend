<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\DeliveryTask;
use App\Models\HomeVisit;
use App\Models\Order;
use App\Models\Rating;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

class RatingScenarioSeeder extends Seeder
{
    /**
     * The one demo patient rates every provider role, each tied to the real
     * completed service instance behind it — mirrors exactly what
     * RatingController itself writes (same FK columns, same "only after
     * completed/delivered" rule), just seeded directly instead of via HTTP.
     */
    public function run(): void
    {
        $patient = User::where('email', DemoScenarioData::PATIENT_EMAIL)->first()?->patient;
        $doctor = User::where('email', DemoScenarioData::DOCTOR_EMAIL)->first()?->doctor;
        $pharmacist = User::where('email', DemoScenarioData::PHARMACIST_EMAIL)->first()?->pharmacist;
        $delivery = User::where('email', DemoScenarioData::DELIVERY_EMAIL)->first()?->delivery;

        if (! $patient) {
            $this->command->warn('RatingScenarioSeeder: run PatientSeeder first.');

            return;
        }

        $raterId = $patient->user_id;

        // Doctor — the completed consultation from ConsultationScenarioSeeder.
        $consultation = Consultation::where('patient_id', $patient->id)
            ->where('doctor_id', $doctor?->id)
            ->where('status', 'completed')
            ->first();
        if ($consultation) {
            Rating::updateOrCreate(
                ['user_id' => $raterId, 'target_type' => 'doctor', 'target_id' => $doctor->id],
                ['stars' => 5, 'consultation_id' => $consultation->id]
            );
        }

        // Pharmacist — the delivered order (#6) from PharmacyOrderScenarioSeeder.
        $deliveredOrder = Order::where('patient_id', $patient->id)
            ->where('pharmacist_id', $pharmacist?->id)
            ->where('status', 'delivered')
            ->first();
        if ($deliveredOrder) {
            Rating::updateOrCreate(
                ['user_id' => $raterId, 'target_type' => 'pharmacist', 'target_id' => $pharmacist->id],
                ['stars' => 4, 'order_id' => $deliveredOrder->id]
            );
        }

        // Delivery agent — the delivered task behind that same order.
        $deliveredTask = $deliveredOrder
            ? DeliveryTask::where('order_id', $deliveredOrder->id)->where('status', 'delivered')->first()
            : null;
        if ($deliveredTask && $delivery) {
            Rating::updateOrCreate(
                ['user_id' => $raterId, 'target_type' => 'delivery', 'target_id' => $delivery->id],
                ['stars' => 5, 'delivery_task_id' => $deliveredTask->id]
            );
        }

        // Nurse and physiotherapist — their completed home visits from HomeVisitTestSeeder.
        $completedVisits = HomeVisit::where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->get();
        $starRotation = ['nurse' => 5, 'physiotherapist' => 4];
        foreach ($completedVisits as $visit) {
            Rating::updateOrCreate(
                ['user_id' => $raterId, 'target_type' => 'care_provider', 'target_id' => $visit->care_provider_id],
                ['stars' => $starRotation[$visit->service_type] ?? 4, 'home_visit_id' => $visit->id]
            );
        }

        // Keep the *_avg columns in sync the same way RatingController does
        // after every real submission.
        if ($doctor) {
            $doctor->update(['rating_avg' => round(Rating::where('target_type', 'doctor')->where('target_id', $doctor->id)->avg('stars'), 1)]);
        }
        if ($pharmacist) {
            $avg = Rating::where('target_type', 'pharmacist')->where('target_id', $pharmacist->id)->avg('stars');
            if ($avg !== null && \Illuminate\Support\Facades\Schema::hasColumn('pharmacists', 'rating_avg')) {
                $pharmacist->update(['rating_avg' => round($avg, 1)]);
            }
        }
        foreach (\App\Models\CareProvider::whereIn('id', $completedVisits->pluck('care_provider_id'))->get() as $careProvider) {
            $careProvider->update(['rating_avg' => round(Rating::where('target_type', 'care_provider')->where('target_id', $careProvider->id)->avg('stars'), 1)]);
        }
        if ($delivery) {
            $avg = Rating::where('target_type', 'delivery')->where('target_id', $delivery->id)->avg('stars');
            if ($avg !== null && \Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'rating_avg')) {
                $delivery->update(['rating_avg' => round($avg, 1)]);
            }
        }
    }
}
