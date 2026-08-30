<?php

namespace Database\Seeders;

use App\Models\DeliveryTask;
use App\Models\Medication;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Delivery;
use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use App\Models\User;
use Database\Seeders\Data\DemoScenarioData;
use Illuminate\Database\Seeder;

class PharmacyOrderScenarioSeeder extends Seeder
{
    protected const MARKER = '[seed:pharmacy-order-scenario]';

    /**
     * Six orders for the same patient/pharmacist/delivery-agent pair, each
     * parked at a different real status — a patient plausibly orders from
     * the same pharmacy several times, so this stays realistic while still
     * giving the pharmacist and delivery-agent dashboards a full spread of
     * states to show (not just one row each).
     */
    public function run(): void
    {
        $pharmacist = User::where('email', DemoScenarioData::PHARMACIST_EMAIL)->first()?->pharmacist;
        $delivery = User::where('email', DemoScenarioData::DELIVERY_EMAIL)->first()?->delivery;

        if (! $pharmacist) {
            $this->command->warn('PharmacyOrderScenarioSeeder: run PharmacistSeeder first.');

            return;
        }

        // #1 — a ready-made prescription, not sent to any pharmacy yet.
        $this->makePrescription('created', null, ['Amoxicillin'], $pharmacist, withOrder: false, label: '#1 not sent yet');

        // #2 — order just placed, pharmacist hasn't acted yet.
        $this->makePrescription(
            'sent_to_pharmacy', 'pending',
            ['Paracetamol', 'Vitamin C'], $pharmacist, withOrder: true, label: '#2 pending'
        );

        // #3 — pharmacist accepted, preparing.
        $this->makePrescription(
            'accepted', 'accepted',
            ['Metformin', 'Amlodipine'], $pharmacist, withOrder: true, label: '#3 accepted'
        );

        // #4 — priced and ready for a delivery driver to pick up. No
        // DeliveryTask row at all — DeliveryController::newOrders() only
        // lists orders with whereDoesntHave('deliveryTask'), and accept()
        // is what actually creates the task (jumping straight to
        // 'picking_up_the_order' with a real delivery_id — there is no
        // real "task exists but unassigned" state in this app). This is
        // the real shape of "a new order delivery@gmail.com can accept".
        $this->makePrescription(
            'priced', 'ready_for_delivery',
            ['Cephalexin'], $pharmacist, withOrder: true, label: '#4 ready_for_delivery', price: 45000
        );

        // #5 — assigned to the driver, picking it up from the pharmacy.
        $order5 = $this->makePrescription(
            'priced', 'out_for_delivery',
            ['Azithromycin'], $pharmacist, withOrder: true, label: '#5 picking_up', price: 28000
        );
        if ($order5 && $delivery) {
            DeliveryTask::updateOrCreate(
                ['order_id' => $order5->id],
                ['delivery_id' => $delivery->id, 'status' => 'picking_up_the_order', 'delivery_fee' => 10000, 'assigned_at' => now()->subMinutes(15)]
            );
        }

        // #6 — fully delivered — the "completed order" a patient rating can
        // be attached to.
        $order6 = $this->makePrescription(
            'priced', 'delivered',
            ['Ibuprofen', 'Cholecalciferol'], $pharmacist, withOrder: true, label: '#6 delivered', price: 52000
        );
        if ($order6 && $delivery) {
            DeliveryTask::updateOrCreate(
                ['order_id' => $order6->id],
                [
                    'delivery_id' => $delivery->id,
                    'status' => 'delivered',
                    'delivery_fee' => 10000,
                    'assigned_at' => now()->subDays(1),
                    'picked_at' => now()->subDay()->addMinutes(20),
                    'delivered_at' => now()->subDay()->addHour(),
                ]
            );

            // Mirrors DeliveryController::updateTaskStatus()'s own
            // total_amount += delivery_fee step on the real 'delivered'
            // transition, so the seeded final total matches what the real
            // flow would have produced.
            $order6->update(['total_amount' => $order6->total_amount + 10000]);
        }

        $this->seedOrderHistory($pharmacist, $delivery);
    }

    /**
     * ~46 more delivered orders spread weekly over the past year — real
     * order history for the pharmacist's/delivery agent's "past orders"
     * lists and the patient's own order history, on top of the 5 named
     * showcase states above.
     */
    protected function seedOrderHistory(Pharmacist $pharmacist, ?Delivery $delivery): void
    {
        $patient = User::where('email', DemoScenarioData::PATIENT_EMAIL)->first()?->patient;
        if (! $patient) {
            return;
        }

        $medicationRotation = [
            ['Paracetamol'], ['Amoxicillin', 'Vitamin C'], ['Ibuprofen'], ['Cephalexin'],
            ['Metformin', 'Amlodipine'], ['Azithromycin'], ['Omeprazole'], ['Cholecalciferol'],
        ];
        $priceRotation = [15000, 32000, 18000, 45000, 27000, 28000, 20000, 12000];

        $count = 46;
        for ($i = 0; $i < $count; $i++) {
            $orderedAt = now()->subWeeks($count - $i);
            $deliveredAt = (clone $orderedAt)->addHours(3);
            $meds = $medicationRotation[$i % count($medicationRotation)];
            $price = $priceRotation[$i % count($priceRotation)];
            $label = 'history #' . ($i + 1);

            $prescription = Prescription::updateOrCreate(
                ['notes' => self::MARKER . ' ' . $label],
                [
                    'patient_id' => $patient->id,
                    'pharmacist_id' => $pharmacist->id,
                    'source' => 'doctor_written',
                    'status' => 'priced',
                    'total_price' => $price,
                    'total_quantity' => count($meds),
                ]
            );
            $prescription->forceFill(['created_at' => $orderedAt])->save();

            PrescriptionMedication::where('prescription_id', $prescription->id)->delete();
            foreach ($meds as $name) {
                $medication = Medication::firstOrCreate(['name' => $name], ['dosage' => '']);
                PrescriptionMedication::create([
                    'prescription_id' => $prescription->id,
                    'medication_id' => $medication->id,
                    'boxes' => (string) (1 + $i % 2),
                    'price' => round($price / count($meds), 2),
                ]);
            }

            $order = Order::updateOrCreate(
                ['prescription_id' => $prescription->id],
                [
                    'patient_id' => $patient->id,
                    'pharmacist_id' => $pharmacist->id,
                    'status' => 'delivered',
                    'total_amount' => $price + 10000,
                ]
            );
            $order->forceFill(['created_at' => $orderedAt])->save();

            if ($delivery) {
                $task = DeliveryTask::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'delivery_id' => $delivery->id,
                        'status' => 'delivered',
                        'delivery_fee' => 10000,
                        'assigned_at' => $orderedAt,
                        'picked_at' => (clone $orderedAt)->addHour(),
                        'delivered_at' => $deliveredAt,
                    ]
                );
                $task->forceFill(['created_at' => $orderedAt])->save();
            }
        }
    }

    protected function makePrescription(
        string $prescriptionStatus,
        ?string $orderStatus,
        array $medicationNames,
        Pharmacist $pharmacist,
        bool $withOrder,
        ?string $label = null,
        ?float $price = null,
    ): ?Order {
        $patient = User::where('email', DemoScenarioData::PATIENT_EMAIL)->first()?->patient;
        if (! $patient instanceof Patient) {
            $this->command->warn('PharmacyOrderScenarioSeeder: patient not found — run PatientSeeder first.');

            return null;
        }

        $note = self::MARKER . ' ' . ($label ?? 'order');

        $prescription = Prescription::updateOrCreate(
            ['notes' => $note],
            [
                'patient_id' => $patient->id,
                'pharmacist_id' => $withOrder ? $pharmacist->id : null,
                'source' => 'doctor_written',
                'status' => $prescriptionStatus,
                'total_price' => $price,
                'total_quantity' => count($medicationNames),
            ]
        );

        PrescriptionMedication::where('prescription_id', $prescription->id)->delete();
        foreach ($medicationNames as $name) {
            $medication = Medication::firstOrCreate(['name' => $name], ['dosage' => '']);
            PrescriptionMedication::create([
                'prescription_id' => $prescription->id,
                'medication_id' => $medication->id,
                'boxes' => (string) rand(1, 2),
                'price' => $price ? round($price / max(count($medicationNames), 1), 2) : null,
            ]);
        }

        if (! $withOrder) {
            return null;
        }

        return Order::updateOrCreate(
            ['prescription_id' => $prescription->id],
            [
                'patient_id' => $patient->id,
                'pharmacist_id' => $pharmacist->id,
                'status' => $orderStatus,
                'total_amount' => $price,
            ]
        );
    }
}
