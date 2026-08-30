<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\HomeVisit;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers StripeController — createIntent, webhook, and status.
 *
 * This area had ZERO test coverage before this file (confirmed by grep
 * across tests/ for "StripeController"/"payment_intent" prior to writing
 * this). Stripe's PHP SDK makes its own HTTP calls internally (not through
 * Laravel's Http facade), so Http::fake() cannot intercept it — these
 * tests call Stripe's REAL Test Mode API instead (the real sk_test_/
 * pk_test_ keys already configured in .env), exactly as Stripe Test Mode
 * is meant to be used: no real card is charged, no real money moves.
 * These tests need network access to Stripe's API and are slower than
 * the Http::fake()-based tests elsewhere in this suite — that trade-off
 * is inherent to testing a static SDK with no injected HTTP seam, not
 * something this file works around.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function patientWithConsultation(float $consultationFee = 50.00): array
    {
        $user = User::create([
            'full_name' => 'Payment Test Patient',
            'email' => 'pay-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
            'password' => 'password123',
            'status' => 'approved',
            'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $patient = Patient::create(['user_id' => $user->id, 'gender' => 'female']);

        $doctor = Doctor::factory()->create(['consultation_fee' => $consultationFee]);

        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'schedule',
            'status' => 'pending',
        ]);

        return [$user->fresh(), $consultation];
    }

    /** Stripe's own v1 webhook signing scheme — HMAC-SHA256(secret, "t.{payload}"). */
    private function signedWebhookHeader(string $payload, string $secret): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$signature}";
    }

    // --- createIntent: happy path & amount derivation -------------------------

    public function test_create_intent_derives_amount_from_the_doctors_consultation_fee(): void
    {
        [$user, $consultation] = $this->patientWithConsultation(50.00);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'consultation',
                'payable_id' => $consultation->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', 5000) // $50.00 -> 5000 cents
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonStructure(['data' => ['client_secret', 'payment_intent_id']]);

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'payable_id' => $consultation->id,
            'amount' => 5000,
        ]);
    }

    public function test_create_intent_ignores_a_client_supplied_amount(): void
    {
        // The security property itself: a client cannot pay $0.01 for a $50
        // consultation by sending its own 'amount' field — the controller
        // never reads request input for the charge amount at all.
        [$user, $consultation] = $this->patientWithConsultation(50.00);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'consultation',
                'payable_id' => $consultation->id,
                'amount' => 1,
            ]);

        $response->assertStatus(201)->assertJsonPath('data.amount', 5000);
    }

    /**
     * White Box: StripeController::resolveAmount()'s 'order' branch
     * (payable->total_amount) — never exercised by any prior test, which
     * only ever hit the 'consultation' branch (payable->doctor->consultation_fee).
     */
    public function test_create_intent_derives_amount_from_the_orders_total_amount(): void
    {
        $user = User::create([
            'full_name' => 'Payment Test Patient Order',
            'email' => 'pay-order-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
            'password' => 'password123',
            'status' => 'approved',
            'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $patient = Patient::create(['user_id' => $user->id, 'gender' => 'female']);
        $pharmacist = \App\Models\Pharmacist::factory()->create();
        $prescription = \App\Models\Prescription::create([
            'patient_id' => $patient->id, 'pharmacist_id' => $pharmacist->id,
            'source' => 'patient_uploaded', 'status' => 'priced',
        ]);
        $order = \App\Models\Order::create([
            'prescription_id' => $prescription->id, 'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id, 'status' => 'ready_for_delivery',
            'total_amount' => 30.00,
        ]);

        $response = $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'order',
                'payable_id' => $order->id,
            ]);

        $response->assertStatus(201)->assertJsonPath('data.amount', 3000); // $30.00 -> 3000 cents
    }

    /**
     * White Box: StripeController::resolveAmount()'s 'home_visit' branch
     * (payable->careProvider->session_fee) — never exercised by any prior test.
     */
    public function test_create_intent_derives_amount_from_the_care_providers_session_fee(): void
    {
        $user = User::create([
            'full_name' => 'Payment Test Patient HomeVisit',
            'email' => 'pay-hv-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
            'password' => 'password123',
            'status' => 'approved',
            'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $patient = Patient::create(['user_id' => $user->id, 'gender' => 'female']);
        $careProvider = \App\Models\CareProvider::factory()->create(['session_fee' => 20.00]);
        $homeVisit = HomeVisit::factory()->create([
            'patient_id' => $patient->id, 'care_provider_id' => $careProvider->id,
        ]);

        $response = $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'home_visit',
                'payable_id' => $homeVisit->id,
            ]);

        $response->assertStatus(201)->assertJsonPath('data.amount', 2000); // $20.00 -> 2000 cents
    }

    public function test_create_intent_requires_authentication(): void
    {
        [, $consultation] = $this->patientWithConsultation();

        $this->postJson('/api/payments/intent', [
            'payable_type' => 'consultation',
            'payable_id' => $consultation->id,
        ])->assertStatus(401);
    }

    // --- ownership -------------------------------------------------------------

    public function test_create_intent_rejects_a_patient_who_does_not_own_the_payable(): void
    {
        [, $consultation] = $this->patientWithConsultation();
        [$stranger] = $this->patientWithConsultation();

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'consultation',
                'payable_id' => $consultation->id,
            ])->assertStatus(403);
    }

    // --- business rules ----------------------------------------------------

    public function test_create_intent_rejects_an_already_paid_payable(): void
    {
        [$user, $consultation] = $this->patientWithConsultation();
        $consultation->forceFill(['payment_status' => 'paid'])->save();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'consultation',
                'payable_id' => $consultation->id,
            ])->assertStatus(409);
    }

    public function test_create_intent_rejects_an_amount_below_fifty_cents(): void
    {
        [$user, $consultation] = $this->patientWithConsultation(0.10);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'consultation',
                'payable_id' => $consultation->id,
            ])->assertStatus(422);
    }

    public function test_create_intent_rejects_a_payable_with_no_price_yet(): void
    {
        $user = User::create([
            'full_name' => 'Payment Test Patient', 'email' => 'pay-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999), 'role' => 'patient',
            'password' => 'password123', 'status' => 'approved', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $patient = Patient::create(['user_id' => $user->id, 'gender' => 'female']);

        // A home visit with no care provider assigned yet has no session_fee to derive.
        $homeVisit = HomeVisit::factory()->create(['patient_id' => $patient->id, 'care_provider_id' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'home_visit',
                'payable_id' => $homeVisit->id,
            ])->assertStatus(422);
    }

    public function test_create_intent_rejects_a_payable_type_outside_the_whitelist(): void
    {
        [$user, $consultation] = $this->patientWithConsultation();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'subscription', // not in the payableMap
                'payable_id' => $consultation->id,
            ])->assertStatus(422);
    }

    public function test_create_intent_returns_not_found_for_a_missing_payable(): void
    {
        [$user] = $this->patientWithConsultation();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/intent', [
                'payable_type' => 'consultation',
                'payable_id' => 999999,
            ])->assertStatus(404);
    }

    // --- webhook -----------------------------------------------------------

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        $payload = json_encode(['id' => 'evt_test', 'type' => 'payment_intent.succeeded']);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_Stripe-Signature' => 't=' . time() . ',v1=not-a-real-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
    }

    public function test_webhook_marks_payment_as_paid_on_a_validly_signed_succeeded_event(): void
    {
        [$user, $consultation] = $this->patientWithConsultation();
        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_id' => $consultation->id,
            'payable_type' => Consultation::class,
            'payment_intent_id' => 'pi_test_' . uniqid(),
            'amount' => 5000,
            'currency' => 'USD',
            'status' => 'requires_payment_method',
            'payment_method' => 'card',
        ]);

        $webhookSecret = config('services.stripe.webhook_secret');
        $payload = json_encode([
            'id' => 'evt_test_' . uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => $payment->payment_intent_id,
                'latest_charge' => 'ch_test_123',
            ]],
        ]);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_Stripe-Signature' => $this->signedWebhookHeader($payload, $webhookSecret),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200)->assertJsonPath('received', true);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $consultation->fresh()->payment_status);
    }

    public function test_webhook_ignores_an_event_for_an_unknown_payment_intent(): void
    {
        // Must not throw — just no-op, logged as a warning per the controller's own code.
        $webhookSecret = config('services.stripe.webhook_secret');
        $payload = json_encode([
            'id' => 'evt_test_' . uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_never_issued', 'latest_charge' => null]],
        ]);

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_Stripe-Signature' => $this->signedWebhookHeader($payload, $webhookSecret),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);
    }

    // --- status --------------------------------------------------------------

    public function test_status_is_only_readable_by_the_payment_owner(): void
    {
        [$user, $consultation] = $this->patientWithConsultation();
        [$stranger] = $this->patientWithConsultation();

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_id' => $consultation->id,
            'payable_type' => Consultation::class,
            'payment_intent_id' => 'pi_test_' . uniqid(),
            'amount' => 5000,
            'currency' => 'USD',
            'status' => 'requires_payment_method',
            'payment_method' => 'card',
        ]);

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/payments/status/{$payment->payment_intent_id}")
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/payments/status/{$payment->payment_intent_id}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'requires_payment_method');
    }

    public function test_status_for_an_unknown_intent_id_returns_not_found(): void
    {
        [$user] = $this->patientWithConsultation();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payments/status/pi_never_issued')
            ->assertStatus(404);
    }
}
