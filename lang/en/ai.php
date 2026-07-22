<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Module (Chat, Speech, DDI, Lab, Interview)
    |--------------------------------------------------------------------------
    |
    | IMPORTANT: Only user-facing wrapper messages live here. Clinical payloads
    | returned by the Python microservices — drug names, scientific names, drug
    | identifiers, medical codes, report bodies — are passed through untouched
    | and are never translated.
    |
    */

    /*
    | Shared AI service errors
    */

    'service_failed' => 'AI service request failed.',
    'service_unavailable' => 'AI service is unavailable.',
    'service_timeout' => 'AI service request timed out.',
    'service_invalid_response' => 'AI service returned an invalid response.',
    'service_connection_failed' => 'Unable to connect to :service.',
    'service_request_failed' => ':service request failed with status :status.',
    'service_unavailable_named' => ':service is unavailable after multiple attempts.',
    'service_invalid_json' => ':service response is not valid JSON.',
    'service_download_failed' => ':service file download failed with status :status.',

    // Keys mirror the $serviceLabelKey on each FastApiClient subclass.
    'service_label_medical_assistant' => 'Medical Assistant service',
    'service_label_ddi' => 'Drug interaction service',
    'service_label_lab' => 'Lab analysis service',

    /*
    | Chat / conversations
    */

    'chat_started' => 'Chat session started successfully.',
    'chat_response' => 'AI response generated successfully.',
    'conversation_created' => 'Conversation created successfully.',
    'conversation_deleted' => 'Conversation deleted successfully.',
    'conversation_retrieved' => 'Conversation retrieved successfully.',
    'conversations_retrieved' => 'Conversations retrieved successfully.',
    'message_sent' => 'Message sent successfully.',
    'messages_retrieved' => 'Messages retrieved successfully.',
    'conversation_not_authorized' => 'You are not authorized to send messages in this conversation.',
    'conversation_not_found' => 'Conversation not found.',

    /*
    | Speech to text
    */

    'speech_converted' => 'Speech converted successfully.',
    'speech_failed' => 'Speech-to-text conversion failed.',
    'speech_no_text' => 'AI service did not return transcribed text.',
    'speech_storage_failed' => 'Failed to store audio file.',
    'speech_unexpected_error' => 'An unexpected error occurred during speech processing.',

    /*
    | Symptom extraction
    */

    'symptoms_failed' => 'Symptom extraction failed.',
    'symptoms_missing' => 'AI service did not return detected symptoms.',

    /*
    | Drug interaction (DDI)
    */

    'ddi_interaction_completed' => 'Drug interaction check completed.',
    'ddi_batch_completed' => 'Batch drug interaction check completed.',
    'ddi_screen_completed' => 'Medication list screened successfully.',
    'ddi_allergy_completed' => 'Allergy cross-reactivity check completed.',
    'ddi_pregnancy_completed' => 'Pregnancy safety check completed.',
    'ddi_unexpected_error' => 'An unexpected error occurred during the drug safety check.',
    'ddi_no_prediction' => 'DDI service did not return an interaction prediction.',
    'ddi_no_batch_results' => 'DDI service did not return batch results.',
    'ddi_no_findings' => 'DDI service did not return screening findings.',
    'ddi_check_retrieved' => 'Drug interaction check retrieved successfully.',
    'ddi_checks_retrieved' => 'Drug interaction checks retrieved successfully.',
    'ddi_check_not_found' => 'Drug interaction check not found.',
    'ddi_no_resolution' => 'DDI service did not return a resolution result.',

    /*
    | Prescription safety verification
    */

    'verification_completed' => 'Prescription verification completed.',
    'safety_verification_completed' => 'Prescription safety verification completed.',
    'verification_unexpected_error' => 'An unexpected error occurred during safety verification.',

    /*
    | Lab analysis
    */

    'lab_analyzed' => 'Lab report analyzed successfully.',
    'lab_analyses_retrieved' => 'Lab analyses retrieved successfully.',
    'lab_analysis_retrieved' => 'Lab analysis retrieved successfully.',
    'lab_analysis_not_found' => 'Lab analysis not found.',
    'lab_patients_only' => 'Only patients can access lab analyses.',
    'lab_pdf_unavailable' => 'The PDF report is not available for this analysis.',
    'lab_unexpected_error' => 'An unexpected error occurred during the lab analysis.',
    'lab_no_report_id' => 'Lab analysis service did not return a report id.',
    'lab_service_reachable' => 'Lab analysis service is reachable.',
    'lab_supported_tests' => 'Supported tests retrieved successfully.',
    'lab_reference_ranges' => 'Reference ranges retrieved successfully.',

    /*
    | History-taking interview
    */

    'interview_invalid_finished' => 'Interview service did not return a valid "finished" flag.',
    'interview_no_session' => 'Interview service did not return a session id.',
    'interview_no_patient' => 'No patient exists in the database to link the demo conversation to.',

];
