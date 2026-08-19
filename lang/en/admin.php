<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Module
    |--------------------------------------------------------------------------
    */

    'dashboard_retrieved' => 'Dashboard data retrieved successfully.',
    'services_retrieved' => 'Services retrieved successfully.',
    'users_retrieved' => 'Users retrieved successfully.',
    'attachments_retrieved' => 'Attachments retrieved successfully.',

    'user_created' => 'Account created successfully',
    'user_updated' => 'Account updated successfully',
    'user_deleted' => 'Account deleted successfully',
    'user_approved' => 'Account approved and activated',
    'user_rejected' => 'Account rejected successfully',
    'user_already_approved' => 'Account already approved',

    'only_active_can_be_edited' => 'Only active accounts can be edited.',
    'only_active_can_be_deleted' => 'Only active accounts can be deleted.',

    'not_available' => 'N/A',

    /*
    | Service names shown in the admin services/ratings list (services())
    */
    'service_names' => [
        'consultation' => 'Consultation',
        'home_visit_nurse' => 'Home Visit - Nurse',
        'home_visit_physiotherapist' => 'Home Visit - Physiotherapist',
        'medication_delivery' => 'Medication Delivery',
    ],

    /*
    | Display labels for the dashboard() stat blocks — the frontend was
    | showing the raw JSON keys (e.g. "patients", "delivery_agents")
    | directly as UI text with no translation dictionary of its own.
    | Exposed additively as response.labels, mirroring response.data's
    | shape — data's own keys/numbers are untouched, so nothing that
    | already reads them breaks.
    */
    'dashboard_labels' => [
        'users' => 'Users',
        'users_patients' => 'Patients',
        'users_doctors' => 'Doctors',
        'users_pharmacists' => 'Pharmacists',
        'users_nurse' => 'Nurses',
        'users_physiotherapist' => 'Physiotherapists',
        'users_delivery_agents' => 'Delivery Agents',

        'consultations' => 'Consultations',
        'consultations_total' => 'Total',
        'consultations_completed' => 'Completed',
        'consultations_cancelled' => 'Cancelled',

        'orders' => 'Orders',
        'orders_total' => 'Total',
        'orders_delivered' => 'Delivered',
        'orders_pending' => 'Pending',

        'revenue' => 'Revenue',
        'revenue_total' => 'Total Revenue',

        'pending_documents' => 'Pending Documents',

        'top_providers' => 'Top Providers',
        'top_providers_total_consultations' => 'Total Consultations',
    ],

];
