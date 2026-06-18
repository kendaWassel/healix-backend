<?php

namespace App\Services;

use App\Models\Payment;

class PaymentService
{
    protected const VALID_STATUSES = ['pending', 'paid', 'failed', 'cancelled'];

    public function getPaymentStatus(Payment $payment): string
    {
        if (!in_array($payment->status, self::VALID_STATUSES, true)) {
            return 'pending';
        }

        return $payment->status;
    }
}
