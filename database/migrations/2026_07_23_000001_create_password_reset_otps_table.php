<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OTP-based password reset storage.
 *
 * Replaces the email-link flow, which required the user to leave the mobile app
 * and come back through a deep link. A 6-digit code can be typed in-app.
 *
 * Two columns beyond the requested shape carry the handoff between step 2
 * (verify OTP) and step 3 (set new password): once an OTP is verified the row
 * is re-issued as a short-lived reset token. Without them, step 3 would have to
 * trust the email alone, which would let anyone who knows an address reset it.
 *
 * Nothing secret is stored in the clear: both the OTP and the reset token are
 * hashed, so a database leak yields neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();

            // Nullable, and nullOnDelete rather than cascade: keeping the row
            // after a user is deleted preserves the rate-limit/attempt history
            // that the email column is keyed on.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('email')->index();
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            // Issued only after the OTP is verified; consumed by reset-password.
            $table->string('reset_token_hash')->nullable();
            $table->timestamp('reset_token_expires_at')->nullable();

            $table->timestamps();

            // Every lookup is "newest live row for this email".
            $table->index(['email', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
    }
};
