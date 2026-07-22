<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enum / Constant Display Labels
    |--------------------------------------------------------------------------
    |
    | The raw enum values stored in the database are internal identifiers and
    | are NEVER translated — they keep flowing through the API unchanged so
    | existing clients continue to work. These labels are the human-readable
    | counterparts, exposed additively as `*_label` fields by API Resources.
    |
    */

    'role' => [
        'patient' => 'Patient',
        'doctor' => 'Doctor',
        'pharmacist' => 'Pharmacist',
        'care_provider' => 'Care provider',
        'nurse' => 'Nurse',
        'physiotherapist' => 'Physiotherapist',
        'delivery' => 'Delivery driver',
        'admin' => 'Administrator',
    ],

    'gender' => [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ],

    'account_status' => [
        'pending' => 'Pending approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'consultation_type' => [
        'call_now' => 'Immediate call',
        'schedule' => 'Scheduled',
    ],

    'consultation_status' => [
        'pending' => 'Pending',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'home_visit_status' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled',
        'rescheduled' => 'Rescheduled',
    ],

    'service_type' => [
        'nurse' => 'Nursing',
        'physiotherapist' => 'Physiotherapy',
    ],

    'prescription_source' => [
        'doctor_written' => 'Written by doctor',
        'patient_uploaded' => 'Uploaded by patient',
    ],

    'prescription_status' => [
        'created' => 'Created',
        'sent_to_pharmacy' => 'Sent to pharmacy',
        'pending' => 'Being processed',
        'accepted' => 'Accepted',
        'priced' => 'Priced',
        'rejected' => 'Rejected',
    ],

    'order_status' => [
        'pending' => 'Pending',
        'sent_to_pharmacy' => 'Sent to pharmacy',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'ready_for_delivery' => 'Ready for delivery',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
    ],

    'delivery_task_status' => [
        'pending' => 'Pending',
        'picking_up_the_order' => 'Picking up the order',
        'on_the_way' => 'On the way',
        'delivered' => 'Delivered',
    ],

    'delivery_candidate_status' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
    ],

    'payment_status' => [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],

    'message_sender' => [
        'patient' => 'Patient',
        'assistant' => 'Assistant',
    ],

    'message_type' => [
        'text' => 'Text',
        'voice' => 'Voice',
    ],

    'message_status' => [
        'uploaded' => 'Uploaded',
        'transcribed' => 'Transcribed',
        'failed' => 'Failed',
    ],

    'lab_severity' => [
        'normal' => 'Normal',
        'mild' => 'Mild',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
        'critical' => 'Critical',
    ],

    'triage' => [
        'High' => 'High priority',
        'Medium' => 'Medium priority',
        'Low' => 'Low priority',
    ],

    // Interaction severity reported by the DDI microservice. The raw value is
    // passed through untranslated; this is only the display label.
    'ddi_severity' => [
        'none' => 'No known interaction',
        'minor' => 'Minor',
        'moderate' => 'Moderate',
        'major' => 'Major',
        'contraindicated' => 'Contraindicated',
        'unknown' => 'Unknown',
    ],

    // Honorifics used when composing a person's display name.
    'title' => [
        'doctor_prefix' => 'Dr.',
    ],

];
