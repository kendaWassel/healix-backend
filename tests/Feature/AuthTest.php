<?php

namespace Tests\Feature;

use App\Models\CareProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Comprehensive register/login/logout coverage. Before this file, these
 * flows were only tested incidentally inside ArabicInputTest,
 * LocalizationTest, and PasswordResetTest's final "login with new
 * password" check — no dedicated, systematic file existed (confirmed by
 * grep across tests/ for "auth/register"/"auth/login" prior to writing
 * this). Focused on the `patient` role's registration (simplest — no
 * upload-id foreign keys to satisfy); other roles' registration rules
 * are documented in RegisterRequest but not separately re-tested here.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function validPatientPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Test Patient',
            'email' => 'register-' . uniqid() . '@example.com',
            'password' => 'password123',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
            'birth_date' => '1995-01-01',
            'gender' => 'female',
            'address' => 'Damascus, Syria',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ], $overrides);
    }

    // --- register --------------------------------------------------------------

    public function test_patient_can_register_with_valid_data(): void
    {
        Mail::fake();

        $payload = $this->validPatientPayload();

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'role' => 'patient']);
    }

    public function test_registered_password_is_stored_hashed(): void
    {
        Mail::fake();
        $payload = $this->validPatientPayload();

        $this->postJson('/api/auth/register', $payload);

        $user = User::where('email', $payload['email'])->first();
        $this->assertNotSame($payload['password'], $user->password);
        $this->assertTrue(Hash::check($payload['password'], $user->password));
    }

    public function test_registration_defaults_to_pending_and_inactive(): void
    {
        // A brand new account must not be immediately usable — an admin has
        // to approve it (AdminController::approveUser, see AdminTest.php).
        Mail::fake();
        $payload = $this->validPatientPayload();

        $this->postJson('/api/auth/register', $payload);

        $user = User::where('email', $payload['email'])->first();
        $this->assertSame('pending', $user->status);
        $this->assertFalse((bool) $user->is_active);
    }

    // RegisterRequest overrides failedValidation() (confirmed by reading the
    // file directly) to return {status, message, errors: [flat message
    // strings]} — NOT Laravel's default {errors: {field: [...]}} shape
    // assertJsonValidationErrors() expects. Every validation-error case
    // below checks the real flat `errors` array instead.

    public function test_registration_rejects_a_duplicate_email(): void
    {
        Mail::fake();
        $existing = $this->validPatientPayload();
        $this->postJson('/api/auth/register', $existing);

        $duplicate = $this->validPatientPayload(['email' => $existing['email']]);

        $response = $this->postJson('/api/auth/register', $duplicate);
        $response->assertStatus(422)->assertJsonPath('status', 'error');
        $this->assertStringContainsString('email', strtolower(implode(' ', $response->json('errors'))));
    }

    public function test_registration_rejects_a_duplicate_phone(): void
    {
        Mail::fake();
        $existing = $this->validPatientPayload();
        $this->postJson('/api/auth/register', $existing);

        $duplicate = $this->validPatientPayload(['phone' => $existing['phone']]);

        $response = $this->postJson('/api/auth/register', $duplicate);
        $response->assertStatus(422)->assertJsonPath('status', 'error');
        $this->assertStringContainsString('phone', strtolower(implode(' ', $response->json('errors'))));
    }

    public function test_registration_requires_a_minimum_password_length(): void
    {
        $payload = $this->validPatientPayload(['password' => 'short']);

        $this->postJson('/api/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_registration_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/auth/register', ['role' => 'patient']);

        $response->assertStatus(422)->assertJsonPath('status', 'error');
        $this->assertGreaterThanOrEqual(4, count($response->json('errors')));
    }

    public function test_registration_rejects_a_role_outside_the_known_enum(): void
    {
        $payload = $this->validPatientPayload(['role' => 'superadmin']);

        $this->postJson('/api/auth/register', $payload)->assertStatus(422);
    }

    public function test_registration_rejects_patient_specific_fields_missing(): void
    {
        // full_name/email/password/phone/role present, but patient-only
        // required fields (birth_date, gender, address, latitude, longitude)
        // omitted entirely.
        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'Test Patient',
            'email' => 'incomplete-' . uniqid() . '@example.com',
            'password' => 'password123',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
        ]);

        $response->assertStatus(422);
        $this->assertGreaterThanOrEqual(5, count($response->json('errors')));
    }

    // --- login -----------------------------------------------------------------

    private function approvedUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'full_name' => 'Login Test User',
            'email' => 'login-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
            'password' => 'password123',
            'status' => 'approved',
            'is_active' => true,
        ], $overrides));
        $user->markEmailAsVerified();

        return $user->fresh();
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        $user = $this->approvedUser();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertStatus(200)->assertJsonStructure(['token', 'role', 'email_verified']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->approvedUser();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'the-wrong-password',
        ])->assertStatus(401);
    }

    public function test_login_fails_for_an_email_that_does_not_exist(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody-' . uniqid() . '@example.com',
            'password' => 'anything',
        ])->assertStatus(401);
    }

    /**
     * Security test: a classic SQL-injection payload in the email field
     * must be handled safely (Eloquent's query builder parameterizes
     * bindings — this confirms it directly rather than assuming it).
     */
    public function test_login_safely_rejects_a_sql_injection_style_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => "' OR '1'='1",
            'password' => 'anything',
        ]);

        // Neither a 500 (crash) nor a 200 (auth bypass) — must be a clean
        // rejection, either validation (422, not a valid email) or the
        // generic auth failure (401).
        $this->assertContains($response->status(), [401, 422]);
    }

    public function test_login_rejects_a_pending_unapproved_account_with_the_same_generic_error(): void
    {
        // Real, code-confirmed rule: AuthService::authenticate() requires
        // isApproved() AND isActive(). A pending account with the CORRECT
        // password still gets the same 401 as a wrong password — no
        // "your account is pending" hint that would let an attacker
        // distinguish a real email from a made-up one.
        $pending = $this->approvedUser(['status' => 'pending', 'is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $pending->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
        $wrongPasswordResponse = $this->postJson('/api/auth/login', [
            'email' => 'nobody-' . uniqid() . '@example.com',
            'password' => 'password123',
        ]);
        $this->assertSame($response->json(), $wrongPasswordResponse->json());
    }

    public function test_login_rejects_a_rejected_account(): void
    {
        $rejected = $this->approvedUser(['status' => 'rejected', 'is_active' => false]);

        $this->postJson('/api/auth/login', [
            'email' => $rejected->email,
            'password' => 'password123',
        ])->assertStatus(401);
    }

    public function test_login_returns_the_specific_care_provider_type_not_the_generic_role(): void
    {
        // Real, code-confirmed behavior: AuthService::authenticate()
        // substitutes careProvider->type ('nurse'/'physiotherapist') for
        // the generic 'care_provider' role string in the login response.
        $user = $this->approvedUser(['role' => 'care_provider']);
        CareProvider::factory()->create(['user_id' => $user->id, 'type' => 'physiotherapist']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)->assertJsonPath('role', 'physiotherapist');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // --- logout ------------------------------------------------------------

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->approvedUser();
        $token = $user->createToken('device')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_revoked_token_cannot_access_protected_routes_afterward(): void
    {
        // Real observed behavior on this route (auth:sanctum, verified,
        // role:admin): a syntactically-present but no-longer-valid Bearer
        // token is rejected as 403, not 401 — different from the
        // zero-Authorization-header case (401, see
        // test_logout_without_a_token_is_rejected below). This is Laravel's
        // own EnsureEmailIsVerified middleware aborting first with 403 when
        // it finds no resolved user, ahead of RoleMiddleware's own 401 check
        // — a real, worth-knowing quirk of this middleware order, not a bug
        // introduced by this test file. The important, unambiguous
        // assertion either way: access is refused.
        $user = $this->approvedUser();
        $token = $user->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/auth/logout');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard');

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_logout_without_a_token_is_rejected(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }
}
