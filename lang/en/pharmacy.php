<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prescriptions, Orders and Pharmacy Module
    |--------------------------------------------------------------------------
    */

    /*
    | Prescriptions
    */

    'prescription_created' => 'Prescription created',
    'prescription_uploaded' => 'Prescription uploaded successfully',
    'prescription_sent' => 'Prescription sent to pharmacy',
    'prescription_accepted' => 'Prescription accepted successfully',
    'prescription_rejected' => 'Prescription rejected successfully',
    'prescription_not_found' => 'Prescription not found.',
    'prescription_retrieved' => 'Prescription retrieved successfully.',
    'prescriptions_retrieved' => 'Prescriptions retrieved successfully.',

    'prescription_accept_failed' => 'Failed to accept prescription.',
    'prescription_reject_failed' => 'Failed to reject prescription.',
    'prescription_cannot_accept' => 'Prescription cannot be accepted. Current status: :status',
    'prescription_cannot_reject' => 'Prescription cannot be rejected. Current status: :status',
    'prescription_not_authorized_accept' => 'You are not authorized to accept this prescription.',
    'prescription_not_authorized_reject' => 'You are not authorized to reject this prescription.',
    'prescription_not_authorized' => 'Prescription not authorized for this pharmacist.',

    'no_prescriptions' => 'No prescriptions found.',
    'no_prescriptions_for_patient' => 'No prescriptions found for this patient.',
    'no_prescriptions_with_pricing' => 'No prescriptions with pricing found.',

    /*
    | Pricing
    */

    'prices_added' => 'Prices added successfully',
    'prices_add_failed' => 'Failed to add prices.',
    'already_priced' => 'Prescription is already priced.',
    'must_accept_before_pricing' => 'Prescription must be accepted before adding prices.',

    /*
    | Orders
    */

    'order_not_found' => 'Order not found',
    'order_not_accessible' => 'Order not found or not accessible.',
    'order_not_available' => 'Order not available',
    'order_ready' => 'Order marked as ready for delivery',
    'order_ready_failed' => 'Failed to mark order as ready for delivery',
    'order_must_be_accepted' => 'Order must be accepted before marking as ready for delivery',
    'orders_retrieved' => 'Orders retrieved successfully.',

    /*
    | Pharmacies
    */

    'pharmacies_retrieved' => 'Pharmacies retrieved successfully.',
    'pharmacy_retrieved' => 'Pharmacy details retrieved successfully.',
    'pharmacy_closed' => 'Selected pharmacy is currently closed, please choose another one.',
    'no_order_for_prescription' => 'No order found for this prescription',

    /*
    | Doctor-side prescription rules (DoctorService)
    */

    'only_doctors_create_prescriptions' => 'Unauthorized - only doctors can create prescriptions.',
    'only_completed_consultations' => 'Only completed consultations can have prescriptions.',

];
