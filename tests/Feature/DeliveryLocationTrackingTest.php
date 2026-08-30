<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\DeliveryTask;
use App\Models\Order;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * White Box coverage for DeliveryLocationService::getAuthorizedTaskForTracking()
 * (Phase 2 priority #4 — Tracking authorization). Confirmed by grep this
 * had ZERO test coverage before this file, despite being the exact
 * ownership gate deciding who can poll a driver's live GPS location.
 *
 * Real, code-confirmed branch: only the delivery driver ASSIGNED to the
 * task, or the PATIENT who owns the underlying order, may read the
 * location — resolved via two entirely separate queries dispatched on
 * $user->delivery / $user->patient (DeliveryLocationService.php).
 */
class DeliveryLocationTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function taskWithLocation(): array
    {
        $delivery = Delivery::factory()->create();
        $patient = Patient::factory()->create();
        $order = Order::factory()->create(['status' => 'out_for_delivery', 'patient_id' => $patient->id]);
        $task = DeliveryTask::create([
            'order_id' => $order->id,
            'delivery_id' => $delivery->id,
            'status' => 'on_the_way',
        ]);
        DeliveryLocation::create([
            'task_id' => $task->id,
            'delivery_id' => $delivery->id,
            'latitude' => 33.5,
            'longitude' => 36.3,
        ]);

        return [$delivery, $patient, $task];
    }

    public function test_the_owning_patient_can_read_the_tracking_location(): void
    {
        [, $patient, $task] = $this->taskWithLocation();

        $response = $this->actingAs($patient->user, 'sanctum')
            ->getJson("/api/patient/delivery/location/{$task->id}");

        $response->assertStatus(200)->assertJsonPath('status', 'success');
    }

    public function test_the_assigned_driver_can_read_the_tracking_location(): void
    {
        [$delivery, , $task] = $this->taskWithLocation();

        $response = $this->actingAs($delivery->user, 'sanctum')
            ->getJson("/api/delivery/location/{$task->id}");

        $response->assertStatus(200)->assertJsonPath('status', 'success');
    }

    public function test_an_unrelated_patient_cannot_read_someone_elses_tracking_location(): void
    {
        [, , $task] = $this->taskWithLocation();
        $otherPatient = Patient::factory()->create();

        $response = $this->actingAs($otherPatient->user, 'sanctum')
            ->getJson("/api/patient/delivery/location/{$task->id}");

        $response->assertStatus(404);
    }

    public function test_an_unrelated_driver_cannot_read_a_task_not_assigned_to_them(): void
    {
        [, , $task] = $this->taskWithLocation();
        $otherDelivery = Delivery::factory()->create();

        $response = $this->actingAs($otherDelivery->user, 'sanctum')
            ->getJson("/api/delivery/location/{$task->id}");

        $response->assertStatus(404);
    }
}
