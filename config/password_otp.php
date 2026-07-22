<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Password Reset
    |--------------------------------------------------------------------------
    |
    | Tuning for the OTP-based password reset flow. Kept in config rather than
    | hardcoded so the expiry and attempt caps can be tightened in production
    | without a code change.
    |
    */

    // Digits in the generated code. Six is what the mobile UI is built for;
    // changing it means changing the keypad too.
    'length' => 6,

    // Minutes an OTP stays usable. Short enough to limit a leaked-inbox window,
    // long enough to survive slow mail delivery.
    'expiry_minutes' => (int) env('PASSWORD_OTP_EXPIRY_MINUTES', 10),

    // Wrong guesses allowed per OTP before it is burned. With a 6-digit code
    // this caps an online guess at 5 in 1,000,000.
    'max_attempts' => (int) env('PASSWORD_OTP_MAX_ATTEMPTS', 5),

    // Minutes the post-verification reset token stays usable. Only has to cover
    // "type a new password twice".
    'reset_token_expiry_minutes' => (int) env('PASSWORD_OTP_RESET_TOKEN_EXPIRY_MINUTES', 15),

    // Seconds a caller must wait between OTP requests for the same address.
    // Stops mailbox flooding without a shared cache.
    'resend_cooldown_seconds' => (int) env('PASSWORD_OTP_RESEND_COOLDOWN_SECONDS', 60),

];
