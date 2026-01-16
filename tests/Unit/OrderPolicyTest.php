<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Delivery;
use App\Models\DeliveryTask;
use App\Policies\OrderPolicy;

class OrderPolicyTest extends TestCase
{
    /**
     * UNIT TEST: Permission rules for viewing orders
     */
    public function test_patient_can_view_own_order()
    {
        $policy = new OrderPolicy();
        $patient = Patient::factory()->create();
        $user = $patient->user;
        $order = Order::factory()->create(['patient_id' => $patient->id]);

        $this->assertTrue($policy->view($user, $order));
    }

    /**
     * UNIT TEST: Permission rules for viewing orders
     */
    public function test_pharmacist_can_view_assigned_order()
    {
        $policy = new OrderPolicy();
        $pharmacist = Pharmacist::factory()->create();
        $user = $pharmacist->user;
        $order = Order::factory()->create(['pharmacist_id' => $pharmacist->id]);

        $this->assertTrue($policy->view($user, $order));
    }

    /**
     * UNIT TEST: Permission rules for viewing orders
     */
    public function test_delivery_can_view_assigned_order()
    {
        $policy = new OrderPolicy();
        $delivery = Delivery::factory()->create();
        $user = $delivery->user;
        $order = Order::factory()->create();
        $deliveryTask = DeliveryTask::factory()->create([
            'order_id' => $order->id,
            'delivery_id' => $delivery->id,
        ]);

        $this->assertTrue($policy->view($user, $order));
    }

    /**
     * UNIT TEST: Permission rules for creating orders
     */
    public function test_only_admin_can_create_orders()
    {
        $policy = new OrderPolicy();
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = Patient::factory()->create();
        $patientUser = $patient->user;

        $this->assertTrue($policy->create($admin));
        $this->assertFalse($policy->create($patientUser));
    }
}