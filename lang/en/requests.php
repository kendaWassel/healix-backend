<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Per-Request Validation Messages
    |--------------------------------------------------------------------------
    |
    | Each FormRequest keeps its own wording, so these are namespaced by
    | request instead of living in validation.custom. That is deliberate:
    | several requests define different wording for the same attribute+rule
    | pair (for example password.min is 6 on login but 8 on registration),
    | which a single global validation.custom block cannot express.
    |
    */

    'login' => [
        'email_required' => 'Email is required',
        'email_email' => 'Please provide a valid email address',
        'email_max' => 'Email cannot exceed 255 characters',
        'password_required' => 'Password is required',
        'password_min' => 'Password must be at least 6 characters',
    ],

    'register' => [
        'email_unique' => 'Email already exists',
        'phone_unique' => 'Phone number already exists',
        'password_min' => 'Password must be at least 8 characters',
        'role_in' => 'Invalid role selected',
        'gender_in' => 'Gender must be either male or female',
        'type_in' => 'Care provider type must be either nurse or physiotherapist',
        'is_pregnant_in' => 'Pregnancy answer must be yes or no.',
    ],

    'forgot_password' => [
        'email_required' => 'Email is required',
        'email_email' => 'Please provide a valid email address',
        'email_max' => 'Email cannot exceed 255 characters',
    ],

    'verify_reset_otp' => [
        'email_required' => 'Email is required',
        'email_email' => 'Please provide a valid email address',
        'otp_required' => 'The verification code is required.',
        'otp_digits' => 'The verification code must be :digits digits.',
    ],

    'reset_password' => [
        'token_required' => 'The reset token is required.',
        'email_required' => 'Email is required',
        'email_email' => 'Please provide a valid email address',
        'password_required' => 'Password is required',
        'password_min' => 'Password must be at least 8 characters',
        'password_confirmed' => 'The password confirmation does not match.',
    ],

    'book_consultation' => [
        'doctor_id_required' => 'A doctor is required for booking a consultation.',
        'doctor_id_exists' => 'Selected doctor does not exist.',
        'call_type_required' => 'Call type is required.',
        'call_type_in' => 'Call type must be either call_now or schedule.',
        'scheduled_at_date' => 'Scheduled at must be a valid date and time format.',
    ],

    'create_prescription' => [
        'medicines_required' => 'At least one medication must be provided.',
        'medicines_array' => 'Medications must be an array list.',
    ],

    'delivery_location' => [
        'task_id_required' => 'Task id is required.',
        'task_id_integer' => 'Task id must be a valid integer.',
        'task_id_exists' => 'The specified delivery task does not exist.',
        'latitude_required' => 'Latitude is required.',
        'latitude_numeric' => 'Latitude must be a numeric value.',
        'latitude_between' => 'Latitude must be between -90 and 90.',
        'longitude_required' => 'Longitude is required.',
        'longitude_numeric' => 'Longitude must be a numeric value.',
        'longitude_between' => 'Longitude must be between -180 and 180.',
    ],

    'rate' => [
        'stars_required' => 'Rating stars are required.',
        'stars_integer' => 'Stars must be an integer.',
        'stars_min' => 'Stars must be at least 1.',
        'stars_max' => 'Stars must be at most 5.',
    ],

    'conversation' => [
        'title_required' => 'Conversation title is required.',
        'title_string' => 'Conversation title must be a string.',
        'title_max' => 'Conversation title must not exceed 255 characters.',
    ],

    'message' => [
        'message_required' => 'Message content is required.',
        'message_string' => 'Message must be a string.',
        'message_max' => 'Message must not exceed 5000 characters.',
    ],

    'speech' => [
        'conversation_id_required' => 'Conversation ID is required.',
        'conversation_id_exists' => 'The selected conversation does not exist.',
        'audio_required' => 'Audio file is required.',
        'audio_file' => 'The uploaded item must be a valid audio file.',
        'audio_max' => 'Audio file size must not exceed 10MB.',
        'audio_mimes' => 'Audio must be one of: m4a, mp3, wav, ogg, webm.',
        'audio_mimetypes' => 'Audio must be a supported audio format.',
    ],

    'medical_record' => [
        'diagnosis_string' => 'Diagnosis must be a string.',
        'treatment_plan_string' => 'Treatment plan must be a string.',
        'current_medications_string' => 'Current medications must be a string.',
        'chronic_diseases_string' => 'Chronic diseases must be a string.',
        'previous_surgeries_string' => 'Previous surgeries must be a string.',
        'allergies_string' => 'Allergies must be a string.',
        'other_conditions_string' => 'Other conditions must be a string.',
        'attachments_array' => 'Attachments must be an array.',
        'attachments_integer' => 'Each attachment ID must be an integer.',
        'attachments_exists' => 'Each attachment must exist.',
    ],

    'pregnancy_info' => [
        'is_pregnant_required' => 'Please indicate whether the patient is currently pregnant.',
    ],

    'upload' => [
        'file_required' => 'File is required.',
        'file_file' => 'The uploaded item must be a file.',
        'file_max' => 'File size must not exceed 10MB.',
        'image_required' => 'Image is required.',
        'image_image' => 'The file must be an image.',
        'image_mimes' => 'Image must be jpeg, png, jpg, or gif.',
        'image_max' => 'Image size must not exceed 5MB.',
        'category_required' => 'Category is required.',
        'category_string' => 'Category must be a string.',
        'category_in' => 'Category must be one of: certificate, report, document, prescription, profile.',
    ],

    'ddi_allergy' => [
        'drug_required' => 'The drug the patient is allergic to is required.',
    ],

    'ddi_batch' => [
        'pairs_required' => 'At least one drug pair is required.',
        'pairs_max' => 'A maximum of 50 drug pairs can be checked per request.',
        'drug_a_required' => 'Each pair must include the first drug name.',
        'drug_b_required' => 'Each pair must include the second drug name.',
    ],

    'ddi_interaction' => [
        'drug_a_required' => 'The first drug name is required.',
        'drug_b_required' => 'The second drug name is required.',
        'drug_b_different' => 'Please provide two different drug names.',
    ],

    'ddi_pregnancy' => [
        'drug_a_required' => 'A drug name is required.',
    ],

    'ddi_resolve' => [
        'name_required' => 'A drug name is required.',
    ],

    'ddi_screen' => [
        'drugs_required' => 'A list of drug names is required.',
        'drugs_min' => 'At least two drugs are required to screen for interactions.',
        'drugs_max' => 'A maximum of 20 drugs can be screened per request.',
        'drugs_distinct' => 'The medication list contains duplicate drug names.',
    ],

    'verify_draft_prescription' => [
        'patient_id_required' => 'A patient is required.',
        'patient_id_exists' => 'The selected patient does not exist.',
        'medications_required' => 'Please enter the medications to verify.',
        'medications_min' => 'At least one medication is required.',
        'medications_max' => 'A maximum of 50 medications can be verified at once.',
        'medication_required' => 'Medication name cannot be empty.',
        'medication_distinct' => 'The medication list contains duplicates.',
    ],

    'verify_prescription' => [
        'medications_required' => 'Please enter the medications to verify and dispense.',
        'medications_min' => 'At least one medication is required.',
        'medications_max' => 'A maximum of 50 medications can be entered at once.',
        'medication_required' => 'Medication name cannot be empty.',
        'medication_distinct' => 'The medication list contains duplicates.',
    ],

    'lab_analyze' => [
        'file_required' => 'A lab test file is required.',
        'file_mimes' => 'Unsupported file type. Supported formats: CSV, Excel (.xlsx, .xls), PDF.',
        'file_max' => 'The lab test file may not be larger than 10 MB.',
        'gender_in' => 'Gender must be male, female, or other.',
    ],

];
