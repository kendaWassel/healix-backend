<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Pharmacist;
use App\Models\User;
use App\Policies\OrderPolicy;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    /**
     * Test that pharmacist can approve/update order assigned to them.
     */
    public function test_pharmacist_can_approve_order()
    {
        // Create mock user and pharmacist
        $user = new User();
        $user->role = 'pharmacist';
        $user->id = 1;

        $pharmacist = new Pharmacist();
        $pharmacist->id = 1;
        $user->pharmacist = $pharmacist;

        // Create mock order assigned to this pharmacist
        $order = new Order();
        $order->id = 1;
        $order->pharmacist_id = $pharmacist->id;

        // Create the policy instance
        $policy = new OrderPolicy();

        // Assert that the pharmacist can update the order
        $this->assertTrue($policy->update($user, $order));
    }

    /**
     * Test that pharmacist cannot approve/update order not assigned to them.
     */
    public function test_pharmacist_cannot_approve_order_not_assigned()
    {
        // Create mock user and pharmacist
        $user = new User();
        $user->role = 'pharmacist';
        $user->id = 1;

        $pharmacist = new Pharmacist();
        $pharmacist->id = 1;
        $user->pharmacist = $pharmacist;

        // Create another pharmacist
        $otherPharmacist = new Pharmacist();
        $otherPharmacist->id = 2;

        // Create mock order assigned to the other pharmacist
        $order = new Order();
        $order->id = 1;
        $order->pharmacist_id = $otherPharmacist->id;

        // Create the policy instance
        $policy = new OrderPolicy();

        // Assert that the pharmacist cannot update the order
        $this->assertFalse($policy->update($user, $order));
    }

}