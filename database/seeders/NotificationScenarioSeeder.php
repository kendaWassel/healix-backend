<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\DeliveryTask;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use App\Notifications\ConsultationRequestedNotification;
use App\Notifications\CriticalDrugWarningNotification;
use App\Notifications\DeliveryDriverNearbyNotification;
use App\Notifications\MedicalReportAddedNotification;
use App\Notifications\PrescriptionAcceptedNotification;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class NotificationScenarioSeeder extends Seeder
{
    /**
     * Fires the app's real Notification classes against the scenario data
     * from the seeders above (~60 rows total), so every row in the
     * `notifications` table is byte-for-byte what the real feature would
     * have written — not a hand-built imitation.
     *
     * Only the patient and doctor accounts get seeded notifications here —
     * checked against app/Notifications/, none of the current classes ever
     * target a pharmacist/nurse/physiotherapist/delivery/admin account, so
     * an empty inbox for those five is the real, correct state, not a gap.
     *
     * Deliberately uses Notification::sendNow($user, $notification,
     * ['database']) instead of $user->notify($notification) — several of
     * these classes' via() also returns 'mail', and this app's .env has real
     * SMTP credentials configured. Plain ->notify() would actually place an
     * outbound send to the real seeded email every time this seeder runs.
     * The explicit ['database'] channel list overrides via() and guarantees
     * only the DB row is written, regardless of what via() declares.
     */
    public function run(): void
    {
        $patientUser = User::where('email', DemoScenarioData::PATIENT_EMAIL)->first();
        $doctorUser = User::where('email', DemoScenarioData::DOCTOR_EMAIL)->first();
        $patient = $patientUser?->patient;
        $doctor = $doctorUser?->doctor;

        if (! $patientUser || ! $doctorUser || ! $patient || ! $doctor) {
            $this->command->warn('NotificationScenarioSeeder: run UserSeeder/PatientSeeder/DoctorSeeder first.');

            return;
        }

        // Notification::sendNow() always inserts — it has no natural
        // updateOrCreate key the way the other scenario seeders do — so a
        // rerun would otherwise pile up more duplicate rows every time.
        // Clear only these two known seeded accounts' own notifications
        // first, so a rerun always lands back at the same set instead of
        // growing.
        foreach ([$patientUser, $doctorUser] as $user) {
            $user->notifications()->delete();
        }

        // 1) Doctor's inbox — one "consultation requested" per consultation
        // (the most recent 25 of ~51), same as real booking traffic would
        // have produced over time.
        $consultations = Consultation::where('patient_id', $patient->id)
            ->where('doctor_id', $doctor->id)
            ->latest('scheduled_at')
            ->take(25)
            ->get();
        foreach ($consultations as $consultation) {
            Notification::sendNow(
                $doctorUser,
                new ConsultationRequestedNotification($consultation, $consultation->patient, $consultation->doctor),
                ['database']
            );
        }

        // 2) Patient's inbox — "prescription accepted" for 15 of the
        // delivered orders (every delivered order was accepted along the
        // way, even though the seeded history jumps straight to 'priced').
        $deliveredOrders = Order::where('patient_id', $patient->id)
            ->where('status', 'delivered')
            ->latest('id')
            ->take(15)
            ->get();
        foreach ($deliveredOrders as $order) {
            $prescription = Prescription::find($order->prescription_id);
            if ($prescription) {
                Notification::sendNow($patientUser, new PrescriptionAcceptedNotification($prescription), ['database']);
            }
        }

        // 3) Patient's inbox — "delivery completed" for the same 15 delivered orders.
        $deliveredTasks = DeliveryTask::whereIn('order_id', $deliveredOrders->pluck('id'))->get();
        foreach ($deliveredTasks as $task) {
            Notification::sendNow($patientUser, new DeliveryDriverNearbyNotification($task), ['database']);
        }

        // 4) Medical report updated -> notifies the patient (one real record).
        $record = $patient->medicalRecords()->latest('id')->first();
        if ($record) {
            Notification::sendNow($patientUser, new MedicalReportAddedNotification($record), ['database']);
        }

        // 5) Critical drug warnings -> the real allergy/condition-flagged
        // prescriptions from PharmacistVerificationSeeder.
        $flaggedPrescriptions = Prescription::where('patient_id', $patient->id)
            ->where(function ($q) {
                $q->where('notes', 'like', '%allergy-warning%')
                    ->orWhere('notes', 'like', '%condition-warning%');
            })
            ->get();
        foreach ($flaggedPrescriptions as $prescription) {
            Notification::sendNow($patientUser, new CriticalDrugWarningNotification($prescription), ['database']);
        }
    }
}
