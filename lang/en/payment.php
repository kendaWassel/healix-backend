<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payments (Stripe)
    |--------------------------------------------------------------------------
    */

    'intent_created' => 'Payment intent created successfully.',
    'intent_not_found' => 'Payment intent not found.',
    'status_retrieved' => 'Payment status retrieved.',
    'status_retrieved_stripe' => 'Payment status retrieved from Stripe.',
    'payable_not_found' => 'Payable resource not found.',
    'webhook_error' => 'Webhook error',
    'failed' => 'The payment could not be processed. Please try again.',

    'not_authorized' => 'You are not authorized to pay for this item.',
    'not_authorized_view' => 'You are not authorized to view this payment.',
    'already_paid' => 'This item has already been paid for.',
    'amount_unavailable' => 'This item does not have a price yet, so it cannot be paid.',
    'amount_too_small' => 'The payable amount is below the minimum accepted by the payment provider.',

];
