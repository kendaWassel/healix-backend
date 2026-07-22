<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notifications (in-app, mail, SMS, WhatsApp)
    |--------------------------------------------------------------------------
    */

    'retrieved' => 'Notifications retrieved successfully.',
    'unread_retrieved' => 'Unread notifications retrieved successfully.',
    'unread_count' => 'Unread notification count retrieved successfully.',
    'marked_read' => 'Notification marked as read',
    'all_marked_read' => 'All notifications marked as read',
    'deleted' => 'Notification deleted',
    'not_found' => 'Notification not found',

    /*
    | Consultation notifications
    */

    'consultation_booked' => 'A new consultation has been booked by :name',
    'consultation_requested' => 'A new consultation has been requested by :name',
    'consultation_requested_subject' => 'New Consultation Requested',
    'consultation_requested_title' => 'New Consultation Requested',

    'consultation_reminder_subject' => 'Consultation Reminder',
    'consultation_reminder_title' => 'Consultation Reminder',
    'reminder_mail_patient' => 'Reminder: You have a consultation with Dr. :name scheduled for :time',
    'reminder_mail_doctor' => 'Reminder: You have a consultation with :name scheduled for :time',
    'reminder_db_patient' => 'You have a consultation with Dr. :name scheduled for :time',
    'reminder_db_doctor' => 'You have a consultation with :name scheduled for :time',
    'reminder_be_ready' => 'Please be ready for the consultation.',
    'reminder_thanks' => 'Thank you for using our platform!',
    'salutation' => 'Healix Team',
    'time_now' => 'now',

    'sms_consultation_requested' => 'New consultation requested by :name.',
    'sms_scheduled_at' => ' Scheduled at: :time',
    'sms_call_type' => ' Type: :type',

    /*
    | Delivery notifications
    */

    'delivery_task_offer' => 'A new delivery task is available near you.',
    'delivery_task_assigned' => 'Your delivery task has been assigned.',

    /*
    | Email verification mail
    */

    'verify_subject' => 'Email Verification',
    'verify_greeting' => 'Welcome to Healix!',
    'verify_hello' => 'Hello :name,',
    'verify_body' => 'Thank you for registering with Healix. To complete your registration and start using our platform, please verify your email address by clicking the button below:',
    'verify_action' => 'Verify Email Address',
    'verify_fallback' => "If the button doesn't work, you can also copy and paste this link into your browser:",
    'verify_footer' => 'If you did not create an account, no further action is required.',

    /*
    | Password reset OTP mail
    */

    'otp_subject' => 'Your Healix password reset code',
    'otp_greeting' => 'Password reset code',
    'otp_hello' => 'Hello :name,',
    'otp_body' => 'We received a request to reset the password for your Healix account. Enter the code below in the app to continue.',
    'otp_expiry' => 'This code will expire in :count minutes.',
    'otp_warning' => 'Never share this code with anyone. Healix staff will never ask you for it.',
    'otp_footer' => 'If you did not request a password reset, you can safely ignore this email and your password will stay unchanged.',

    /*
    | Messaging gateways
    */

    'sms_failed' => 'Failed to send the SMS message.',
    'whatsapp_not_configured' => 'UltraMsg service not configured',
    'whatsapp_invalid_phone' => 'Invalid phone number format',
    'whatsapp_failed' => 'Failed to send message',
    'whatsapp_sent' => 'Message sent successfully',

    /*
    | Consultation booked mail (emails.notifications.consultation-booked)
    */

    'booked_mail_hello' => 'Hello :name',
    'booked_mail_intro' => 'You have a new consultation booked.',
    'booked_mail_patient' => 'Patient Name: :name',
    'booked_mail_type' => 'Consultation Type: :type',
    'booked_mail_scheduled' => 'Scheduled Date & Time: :time',
    'booked_mail_outro' => 'Check our website for more details',
    'booked_mail_thanks' => 'Thanks,',

];
