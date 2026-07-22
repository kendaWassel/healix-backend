<?php

namespace Tests\Feature;

use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Notifications\ResetPasswordOtpNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Covers the three-step OTP password reset flow and its security properties.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const FORGOT = '/api/auth/forgot-password';
    private const VERIFY = '/api/auth/verify-reset-otp';
    private const RESET = '/api/auth/reset-password';

    protected function setUp(): void
    {
        parent::setUp();

        // The per-IP throttles are asserted in their own test; everywhere else
        // they would just cap how many cases each test can run.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function user(array $attributes = []): User
    {
        return User::create(array_merge([
            'full_name' => 'Test User',
            'email' => 'user@example.com',
            'phone' => '0900000001',
            'role' => 'patient',
            'password' => 'old-password-123',
            'status' => 'approved',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    /** Request an OTP and return the plaintext code from the sent mail. */
    private function requestOtp(User $user): string
    {
        Notification::fake();

        $this->postJson(self::FORGOT, ['email' => $user->email])->assertOk();

        $captured = null;

        Notification::assertSentTo($user, ResetPasswordOtpNotification::class,
            function ($notification) use (&$captured) {
                $captured = $notification->otp;

                return true;
            });

        return $captured;
    }

    /** Full happy path up to holding a reset token. */
    private function verifiedResetToken(User $user): string
    {
        $otp = $this->requestOtp($user);

        return $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => $otp])
            ->assertOk()
            ->json('data.reset_token');
    }

    /* ---------------------------------------------------------------
     | Step 1 — request OTP
     |--------------------------------------------------------------- */

    public function test_otp_is_emailed_to_a_registered_user(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->postJson(self::FORGOT, ['email' => $user->email])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        Notification::assertSentTo($user, ResetPasswordOtpNotification::class);
        $this->assertDatabaseCount('password_reset_otps', 1);
    }

    public function test_otp_is_stored_hashed_never_in_plain_text(): void
    {
        $user = $this->user();
        $otp = $this->requestOtp($user);

        $record = PasswordResetOtp::first();

        $this->assertNotSame($otp, $record->otp_hash);
        $this->assertTrue(Hash::check($otp, $record->otp_hash));
        $this->assertStringNotContainsString($otp, json_encode($record->toArray()));
    }

    public function test_generated_otp_is_six_digits(): void
    {
        $otp = $this->requestOtp($this->user());

        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
    }

    public function test_unknown_email_returns_the_same_response_and_sends_nothing(): void
    {
        Notification::fake();
        $user = $this->user();

        $known = $this->postJson(self::FORGOT, ['email' => $user->email]);
        $unknown = $this->postJson(self::FORGOT, ['email' => 'nobody@example.com']);

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json(), $unknown->json());

        Notification::assertSentTimes(ResetPasswordOtpNotification::class, 1);
        $this->assertDatabaseCount('password_reset_otps', 1);
    }

    public function test_requesting_a_new_otp_invalidates_the_previous_one(): void
    {
        $user = $this->user();
        $first = $this->requestOtp($user);

        // Step past the resend cooldown.
        $this->travel(config('password_otp.resend_cooldown_seconds') + 1)->seconds();
        $second = $this->requestOtp($user);

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('password_reset_otps', 1);

        $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => $first])
            ->assertStatus(422);
        $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => $second])
            ->assertOk();
    }

    public function test_resend_cooldown_suppresses_rapid_repeat_requests(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->postJson(self::FORGOT, ['email' => $user->email])->assertOk();
        $this->postJson(self::FORGOT, ['email' => $user->email])->assertOk();

        // Second request is silently ignored — same response, no second email.
        Notification::assertSentTimes(ResetPasswordOtpNotification::class, 1);
    }

    public function test_forgot_password_validates_the_email(): void
    {
        $this->postJson(self::FORGOT, ['email' => 'not-an-email'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson(self::FORGOT, [])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    /* ---------------------------------------------------------------
     | Step 2 — verify OTP
     |--------------------------------------------------------------- */

    public function test_correct_otp_returns_a_reset_token(): void
    {
        $user = $this->user();
        $otp = $this->requestOtp($user);

        $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => $otp])
            ->assertOk()
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['status', 'message', 'data' => ['reset_token', 'expires_in']]);

        $this->assertNotNull(PasswordResetOtp::first()->verified_at);
    }

    public function test_reset_token_is_stored_hashed(): void
    {
        $user = $this->user();
        $token = $this->verifiedResetToken($user);

        $record = PasswordResetOtp::first();

        $this->assertNotSame($token, $record->reset_token_hash);
        $this->assertSame(hash('sha256', $token), $record->reset_token_hash);
    }

    public function test_wrong_otp_is_rejected_and_counts_an_attempt(): void
    {
        $user = $this->user();
        $this->requestOtp($user);

        $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => '000000'])
            ->assertStatus(422)
            ->assertJson(['status' => 'error']);

        $this->assertSame(1, PasswordResetOtp::first()->attempts);
    }

    public function test_attempts_are_capped(): void
    {
        $user = $this->user();
        $otp = $this->requestOtp($user);
        $max = config('password_otp.max_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => '000000']);
        }

        // Even the CORRECT code is refused once the cap is reached.
        $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => $otp])
            ->assertStatus(429);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = $this->user();
        $otp = $this->requestOtp($user);

        $this->travel(config('password_otp.expiry_minutes') + 1)->minutes();

        $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => $otp])
            ->assertStatus(422);
    }

    public function test_otp_cannot_be_verified_twice(): void
    {
        $user = $this->user();
        $otp = $this->requestOtp($user);

        $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => $otp])->assertOk();

        // Replay must not mint a second reset token.
        $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => $otp])
            ->assertStatus(422);
    }

    public function test_otp_is_bound_to_its_own_email(): void
    {
        $victim = $this->user();
        $attacker = $this->user(['email' => 'attacker@example.com', 'phone' => '0900000002']);

        $attackerOtp = $this->requestOtp($attacker);

        $this->postJson(self::VERIFY, ['email' => $victim->email, 'otp' => $attackerOtp])
            ->assertStatus(422);
    }

    public function test_arabic_indic_digits_are_accepted(): void
    {
        $user = $this->user();
        $otp = $this->requestOtp($user);

        // Same code typed on an Arabic keypad.
        $arabicDigits = str_replace(
            mb_str_split('0123456789'),
            mb_str_split('٠١٢٣٤٥٦٧٨٩'),
            $otp
        );

        $this->postJson(self::VERIFY, ['email' => $user->email, 'otp' => $arabicDigits])
            ->assertOk();
    }

    public function test_verify_validates_otp_shape(): void
    {
        $this->postJson(self::VERIFY, ['email' => 'user@example.com', 'otp' => '123'])
            ->assertStatus(422)->assertJsonValidationErrors('otp');

        $this->postJson(self::VERIFY, ['email' => 'user@example.com'])
            ->assertStatus(422)->assertJsonValidationErrors('otp');
    }

    /* ---------------------------------------------------------------
     | Step 3 — reset password
     |--------------------------------------------------------------- */

    public function test_password_is_reset_with_a_valid_reset_token(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->user();
        $token = $this->verifiedResetToken($user);

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk()->assertJson(['status' => 'success']);

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_password_is_stored_hashed(): void
    {
        $user = $this->user();
        $token = $this->verifiedResetToken($user);

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $stored = $user->fresh()->password;
        $this->assertNotSame('brand-new-password', $stored);
        $this->assertTrue(Hash::isHashed($stored));
    }

    public function test_otp_records_are_deleted_after_a_successful_reset(): void
    {
        $user = $this->user();
        $token = $this->verifiedResetToken($user);

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $user = $this->user();
        $token = $this->verifiedResetToken($user);

        $payload = [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ];

        $this->postJson(self::RESET, $payload)->assertOk();

        $this->postJson(self::RESET, array_merge($payload, [
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ]))->assertStatus(422);

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = $this->user();
        $this->verifiedResetToken($user);

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => 'totally-made-up-token',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_password_cannot_be_reset_without_verifying_the_otp(): void
    {
        $user = $this->user();
        $this->requestOtp($user);   // requested but never verified

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => 'guessed-token',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = $this->user();
        $token = $this->verifiedResetToken($user);

        $this->travel(config('password_otp.reset_token_expiry_minutes') + 1)->minutes();

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_reset_token_is_bound_to_its_own_email(): void
    {
        $victim = $this->user();
        $attacker = $this->user(['email' => 'attacker@example.com', 'phone' => '0900000002']);

        $attackerToken = $this->verifiedResetToken($attacker);

        $this->postJson(self::RESET, [
            'email' => $victim->email,
            'reset_token' => $attackerToken,
            'password' => 'hijacked-password',
            'password_confirmation' => 'hijacked-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password-123', $victim->fresh()->password));
    }

    public function test_sanctum_tokens_are_revoked_after_reset(): void
    {
        $user = $this->user();
        $user->createToken('device');
        $this->assertSame(1, $user->tokens()->count());

        $token = $this->verifiedResetToken($user);

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_validates_all_fields(): void
    {
        $this->postJson(self::RESET, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'reset_token', 'password']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = $this->user();
        $token = $this->verifiedResetToken($user);

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'brand-new-password',
            'password_confirmation' => 'a-different-password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_password_must_meet_minimum_length(): void
    {
        $user = $this->user();
        $token = $this->verifiedResetToken($user);

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    /* ---------------------------------------------------------------
     | Localization
     |--------------------------------------------------------------- */

    public function test_messages_are_localized(): void
    {
        $arabic = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson(self::FORGOT, ['email' => 'anyone@example.com'])
            ->json('message');

        $this->assertSame(__('auth.forgot_password_sent', [], 'ar'), $arabic);
    }

    public function test_validation_errors_are_localized(): void
    {
        $english = $this->postJson(self::FORGOT, [])->json('errors.email');
        $arabic = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson(self::FORGOT, [])->json('errors.email');

        $this->assertNotSame($english, $arabic);
    }

    public function test_otp_email_renders_in_arabic_with_rtl(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson(self::FORGOT, ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordOtpNotification::class,
            function ($notification) use ($user) {
                app()->setLocale('ar');
                $rendered = $notification->toMail($user)->render();

                $this->assertStringContainsString('dir="rtl"', $rendered);
                $this->assertStringContainsString($notification->otp, $rendered);
                $this->assertStringContainsString(__('notification.otp_warning', [], 'ar'), $rendered);

                return true;
            });
    }

    /* ---------------------------------------------------------------
     | Maintenance & regressions
     |--------------------------------------------------------------- */

    public function test_prune_command_removes_expired_records(): void
    {
        $user = $this->user();
        $this->requestOtp($user);

        $this->travel(config('password_otp.expiry_minutes') + 1)->minutes();

        $this->artisan('password-otp:prune')->assertSuccessful();

        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_prune_keeps_records_still_inside_their_window(): void
    {
        $this->requestOtp($this->user());

        $this->artisan('password-otp:prune')->assertSuccessful();

        $this->assertDatabaseCount('password_reset_otps', 1);
    }

    public function test_login_works_with_the_new_password_and_not_the_old_one(): void
    {
        $user = $this->user();
        $token = $this->verifiedResetToken($user);

        $this->postJson(self::RESET, [
            'email' => $user->email,
            'reset_token' => $token,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'brand-new-password',
        ])->assertOk()->assertJsonStructure(['token', 'role', 'email_verified']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'old-password-123',
        ])->assertStatus(401);
    }
}
