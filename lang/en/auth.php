<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    /*
    | Healix authentication flow
    */

    'invalid_credentials' => 'Invalid credentials',
    'unauthenticated' => 'Unauthenticated.',
    'unauthorized' => 'Unauthorized.',
    'forbidden' => 'Forbidden: Access denied',
    'logged_out_success' => 'Logged out successfully.',
    'logout_failed' => 'Unable to log out. No active session token was found.',
    
    'registered' => 'User registered successfully. Please check your email for verification.',
    'registration_failed' => 'Registration failed. Please try again.',
    'invalid_role' => 'Invalid role specified',
    'specialization_not_found' => 'Specialization not found: :name',

    /*
    | Email verification
    */

    'email_verified' => 'Email verified successfully',
    'email_already_verified' => 'Email already verified',
    'email_not_verified' => 'Email not verified',
    'email_verification_required' => 'Please verify your email address before accessing this resource',
    'verification_link_invalid' => 'Invalid verification link',
    'verification_link_expired' => 'Invalid or expired verification link',
    'verification_failed' => 'Failed to verify email',
    'verification_sent' => 'A verification link has been sent to your email address.',

    'verify_open_app_title' => 'Opening the app...',
    'verify_open_app_fallback' => "Didn't the app open automatically?",
    'verify_open_app_action' => 'Tap here to open the app',

    /*
    | Account approval / activation
    */

    /*
    | Password reset
    |
    | forgot_password_sent is deliberately generic: it is returned whether or
    | not the address is registered, so the endpoint cannot be used to discover
    | which emails have accounts.
    */

    'forgot_password_sent' => 'If this email exists, a verification code has been sent.',
    'password_reset_success' => 'Your password has been reset successfully. Please sign in with your new password.',
    'password_reset_failed' => 'We could not reset your password. Please request a new verification code.',

    'otp_verified' => 'Verification code confirmed. You can now set a new password.',
    'otp_invalid' => 'The verification code is incorrect.',
    'otp_expired' => 'The verification code has expired. Please request a new one.',
    'otp_too_many_attempts' => 'Too many incorrect attempts. Please request a new verification code.',
    'reset_token_invalid' => 'This reset session is invalid or has expired. Please start again.',

    'account_not_active' => 'Account not fully activated',
    'account_pending' => 'Your account is pending administrator approval.',
    'account_rejected' => 'Your account request has been rejected.',
    'account_inactive' => 'Your account is currently inactive. Please contact support.',

];
