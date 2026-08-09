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
        // Capitalised keys match the exact casing the DDI service returns.
        'Minor' => 'Minor',
        'Moderate' => 'Moderate',
        'Major' => 'Major',
    ],

    // Confidence in the predicted interaction severity (DDI service values).
    'ddi_confidence' => [
        'LOW' => 'Low confidence',
        'MEDIUM' => 'Medium confidence',
        'HIGH' => 'High confidence',
        'UNCERTAIN' => 'Uncertain',
        'OVERRIDE' => 'Clinically verified',
    ],

    // How an allergy conflict was detected (DDI service values).
    'ddi_detected_by' => [
        'direct_match' => 'Direct allergy',
        'pharmacophore_class' => 'Same drug class',
        'structural_similarity' => 'Structural similarity',
        'atc_class' => 'Same ATC class',
        'cross_reactivity' => 'Cross-reactivity',
    ],

    // Risk level of an allergy / cross-reactivity finding.
    'ddi_risk' => [
        'HIGH' => 'High',
        'MODERATE' => 'Moderate',
        'LOW' => 'Low',
    ],

    // Pregnancy risk category (US FDA letter categories).
    'ddi_pregnancy_category' => [
        'X' => 'Category X — contraindicated',
        'D' => 'Category D — positive evidence of risk',
        'D*' => 'Category D* — avoid in late pregnancy',
        'C' => 'Category C — use with caution',
        'B' => 'Category B — relatively safe',
        'A' => 'Category A — safe',
        'N/A' => 'Not available',
        'Unknown' => 'Unknown',
    ],

    'ddi_condition' => [
        'Disease of liver' => 'Liver disease',
        'Kidney disease' => 'Kidney disease',
        'Diabetes mellitus' => 'Diabetes mellitus',
        'Diabetes mellitus type 1' => 'Type 1 diabetes mellitus',
        'Diabetes mellitus type 2' => 'Type 2 diabetes mellitus',
        'Hypertensive disorder' => 'Hypertensive disorder',
        'Asthma' => 'Asthma',
        'Chronic heart failure' => 'Chronic heart failure',
        'Myocardial infarction' => 'Myocardial infarction',
        'Disorder of coronary artery' => 'Coronary artery disease',
        'Angina pectoris' => 'Angina pectoris',
        'Conduction disorder of the heart' => 'Cardiac conduction disorder',
        'Bradycardia' => 'Bradycardia',
        'Low blood pressure' => 'Low blood pressure',
        'Cerebrovascular accident' => 'Cerebrovascular accident',
        'Prolonged QT interval' => 'Prolonged QT interval',
        'Hepatic failure' => 'Hepatic failure',
        'Impaired renal function disorder' => 'Impaired renal function',
        'Disorder of gallbladder' => 'Gallbladder disease',
        'Peptic ulcer' => 'Peptic ulcer',
        'Gastrointestinal ulcer' => 'Gastrointestinal ulcer',
        'Ulcerative colitis' => 'Ulcerative colitis',
        'Acute pancreatitis' => 'Acute pancreatitis',
        'Chronic idiopathic constipation' => 'Chronic idiopathic constipation',
        'Hyperthyroidism' => 'Hyperthyroidism',
        'Hypothyroidism' => 'Hypothyroidism',
        'Hypercholesterolemia' => 'Hypercholesterolemia',
        'Obesity' => 'Obesity',
        'Gout' => 'Gout',
        'Anemia' => 'Anemia',
        'Blood coagulation disorder' => 'Blood coagulation disorder',
        'Thrombocytopenic disorder' => 'Thrombocytopenic disorder',
        'Epilepsy' => 'Epilepsy',
        'Seizure disorder' => 'Seizure disorder',
        'Depressive disorder' => 'Depressive disorder',
        'Psychotic disorder' => 'Psychotic disorder',
        'Myasthenia gravis' => 'Myasthenia gravis',
        'Glaucoma' => 'Glaucoma',
        'Angle-closure glaucoma' => 'Angle-closure glaucoma',
        'Benign prostatic hyperplasia' => 'Benign prostatic hyperplasia',
        'Retention of urine' => 'Urinary retention',
        'Systemic lupus erythematosus' => 'Systemic lupus erythematosus',
        'Porphyria' => 'Porphyria',
        'Decreased respiratory function' => 'Decreased respiratory function',
        'Alcoholism' => 'Alcohol use disorder',
        'Smokes tobacco daily' => 'Daily tobacco use',
    ],

    // Honorifics used when composing a person's display name.
    'title' => [
        'doctor_prefix' => 'Dr.',
    ],

];
