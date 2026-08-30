<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\DeliveryTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FEATURE TEST: Delivery can accept new order
     *
     * Fixed two real bugs found while reviewing this file (both confirmed
     * by reading DeliveryController::accept() and the real migrations
     * directly, not assumed):
     * 1. 'priced' is not a valid `orders.status` enum value at all (see
     *    database/migrations/2025_11_15_200608_create_orders_table.php) —
     *    accept() only matches orders with status='ready_for_delivery'.
     * 2. accept() creates the DeliveryTask with status
     *    'picking_up_the_order', never 'assigned' — 'assigned' isn't a
     *    valid `delivery_tasks.status` value either (see
     *    database/migrations/2025_11_15_200819_create_delivery_tasks_table.php).
     */
    public function test_delivery_can_accept_new_order()
    {
        $delivery = Delivery::factory()->create();
        $order = Order::factory()->create(['status' => 'ready_for_delivery']);

        $this->actingAs($delivery->user);

        $response = $this->postJson("/api/delivery/new-orders/{$order->id}/accept");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                 ]);

        $this->assertDatabaseHas('delivery_tasks', [
            'order_id' => $order->id,
            'delivery_id' => $delivery->id,
            'status' => 'picking_up_the_order',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'out_for_delivery',
        ]);
    }

    /**
     * FEATURE TEST: Delivery updates task status from pending to
     * picking_up_the_order.
     *
     * Fixed three real bugs found while reviewing this file:
     * 1. Wrong URL — the real route (routes/api/delivery.php) is
     *    PUT /delivery/tasks/{task_id}/update-status, not /status.
     * 2. 'assigned'/'picked' are not real delivery_tasks.status values —
     *    the real enum is pending/picking_up_the_order/on_the_way/delivered
     *    (DeliveryController::updateTaskStatus()'s own $allowed transition
     *    map only permits pending -> picking_up_the_order as the first step).
     * 3. The test body was a placeholder (`assertTrue(true)`) that passed
     *    regardless of actual behavior — replaced with real assertions.
     */
    public function test_delivery_can_update_task_status_from_pending_to_picking_up_the_order()
    {
        $delivery = Delivery::factory()->create();
        $order = Order::factory()->create(['status' => 'out_for_delivery']);
        $task = DeliveryTask::create([
            'order_id' => $order->id,
            'delivery_id' => $delivery->id,
            'status' => 'pending',
        ]);

        $this->actingAs($delivery->user);

        $response = $this->putJson("/api/delivery/tasks/{$task->id}/update-status", [
            'status' => 'picking_up_the_order',
        ]);

        $response->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('delivery_tasks', [
            'id' => $task->id,
            'status' => 'picking_up_the_order',
        ]);
    }

    /**
     * White Box: DeliveryController::updateTaskStatus()'s own $allowed
     * transition map only permits pending -> picking_up_the_order as the
     * first step — skipping straight to on_the_way must be rejected.
     */
    public function test_skipping_a_status_transition_is_rejected(): void
    {
        $delivery = Delivery::factory()->create();
        $order = Order::factory()->create(['status' => 'out_for_delivery']);
        $task = DeliveryTask::create([
            'order_id' => $order->id,
            'delivery_id' => $delivery->id,
            'status' => 'pending',
        ]);

        $this->actingAs($delivery->user);

        $response = $this->putJson("/api/delivery/tasks/{$task->id}/update-status", [
            'status' => 'on_the_way',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('delivery_tasks', ['id' => $task->id, 'status' => 'pending']);
    }
}