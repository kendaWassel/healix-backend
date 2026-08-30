<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the DDI (drug-drug interaction) relay routes
 * (DrugInteractionController + DdiService + DdiClient) — the real DDI
 * FastAPI microservice is faked at the HTTP boundary (Http::fake), same
 * approach HealixSpeechTest/HealthQuestionTest already use for their own
 * external services. This area had ZERO test coverage before this file
 * (confirmed by grep across tests/ for "/ddi/" prior to writing this).
 */
class DrugInteractionTest extends TestCase
{
    use RefreshDatabase;

    private function patientUser(): User
    {
        $user = User::create([
            'full_name' => 'DDI Test Patient',
            'email' => 'ddi-' . uniqid() . '@example.com',
            'phone' => '09' . random_int(10000000, 99999999),
            'role' => 'patient',
            'password' => 'password123',
            'status' => 'approved',
            'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        Patient::create(['user_id' => $user->id, 'gender' => 'female']);

        return $user->fresh();
    }

    // --- checkInteraction ----------------------------------------------------

    public function test_check_interaction_relays_to_ddi_service_and_returns_prediction(): void
    {
        $user = $this->patientUser();

        Http::fake([
            '*/interaction*' => Http::response([
                'prediction' => 'interaction_found',
                'severity' => 'major',
                'alternatives' => ['Acetaminophen'],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/interaction', [
                'drug_a' => 'Warfarin',
                'drug_b' => 'Aspirin',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.prediction', 'interaction_found')
            ->assertJsonPath('data.severity', 'major');

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/interaction')
                && $request['drug_a'] === 'Warfarin'
                && $request['drug_b'] === 'Aspirin';
        });
    }

    public function test_check_interaction_with_no_interaction_found_still_returns_success(): void
    {
        // DDI-001-style negative case: two unrelated drugs, no interaction.
        $user = $this->patientUser();

        Http::fake([
            '*/interaction*' => Http::response(['prediction' => 'no_interaction'], 200),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/interaction', ['drug_a' => 'Paracetamol', 'drug_b' => 'Vitamin C'])
            ->assertStatus(200)
            ->assertJsonPath('data.prediction', 'no_interaction');
    }

    public function test_check_interaction_rejects_identical_drug_pair(): void
    {
        $user = $this->patientUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/interaction', ['drug_a' => 'Aspirin', 'drug_b' => 'Aspirin'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('drug_b');
    }

    public function test_check_interaction_requires_authentication(): void
    {
        $this->postJson('/api/ddi/interaction', ['drug_a' => 'Warfarin', 'drug_b' => 'Aspirin'])
            ->assertStatus(401);
    }

    public function test_check_interaction_requires_both_drug_names(): void
    {
        $user = $this->patientUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/interaction', ['drug_a' => 'Aspirin'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('drug_b');
    }

    public function test_check_interaction_is_persisted_to_the_authenticated_users_history(): void
    {
        $user = $this->patientUser();

        Http::fake(['*/interaction*' => Http::response(['prediction' => 'interaction_found'], 200)]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/interaction', ['drug_a' => 'Warfarin', 'drug_b' => 'Aspirin']);

        $this->assertDatabaseHas('drug_interaction_checks', [
            'user_id' => $user->id,
            'check_type' => 'interaction',
        ]);
    }

    // --- checkBatch ------------------------------------------------------------

    public function test_batch_interaction_check_relays_multiple_pairs(): void
    {
        // DDI-003-style: several pairs, one interacting.
        $user = $this->patientUser();

        Http::fake([
            '*/interaction/batch*' => Http::response([
                'results' => [
                    ['drug_a' => 'Warfarin', 'drug_b' => 'Aspirin', 'prediction' => 'interaction_found'],
                    ['drug_a' => 'Paracetamol', 'drug_b' => 'Vitamin C', 'prediction' => 'no_interaction'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/interaction/batch', [
                'pairs' => [
                    ['drug_a' => 'Warfarin', 'drug_b' => 'Aspirin'],
                    ['drug_a' => 'Paracetamol', 'drug_b' => 'Vitamin C'],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.results'));
    }

    // --- screenMedications -------------------------------------------------

    public function test_screen_medications_relays_the_full_list(): void
    {
        $user = $this->patientUser();

        Http::fake([
            '*/screen*' => Http::response(['findings' => [
                ['drug_a' => 'Warfarin', 'drug_b' => 'Aspirin', 'severity' => 'major'],
            ]], 200),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/screen', ['drugs' => ['Warfarin', 'Aspirin', 'Paracetamol']])
            ->assertStatus(200)
            ->assertJsonPath('data.findings.0.severity', 'major');
    }

    // --- checkAllergy: legacy single-drug mode ---------------------------------

    public function test_allergy_check_single_drug_mode_returns_simplified_result(): void
    {
        $user = $this->patientUser();

        Http::fake([
            '*/allergy*' => Http::response([
                'cross_reactive_drugs' => [
                    ['name' => 'Ibuprofen', 'tanimoto' => 0.9, 'detected_by' => 'structure', 'risk' => 'high'],
                ],
                'note' => 'Structurally similar NSAID.',
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/allergy', ['drug' => 'Aspirin']);

        $response->assertStatus(200);
        // simplifyAllergyResult() reduces each cross-reactive drug to its name only.
        $this->assertSame(['Ibuprofen'], $response->json('data.cross_reactive_drugs'));
        $this->assertArrayHasKey('note', $response->json('data'));
    }

    // --- checkAllergy: prescription batch mode ------------------------------

    public function test_allergy_check_prescription_mode_checks_full_medication_list(): void
    {
        // ALLERGY-001-style: a prescribed drug conflicts with a known allergy.
        $user = $this->patientUser();

        Http::fake([
            '*/allergy*' => Http::response([
                'direct_matches' => [
                    ['medication' => 'Panadol', 'matched_ingredient' => 'Paracetamol', 'risk' => 'high', 'note' => 'x', 'allergen' => 'Paracetamol'],
                ],
                'cross_reactive_matches' => [],
                'safe' => false,
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/allergy', [
                'medications' => ['Panadol'],
                'allergies' => ['Paracetamol'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.safe', false)
            ->assertJsonPath('data.direct_matches.0.medication', 'Panadol');
    }

    public function test_allergy_check_requires_either_mode_to_be_complete(): void
    {
        // Neither a single drug, nor a complete medications+allergies pair.
        $user = $this->patientUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/allergy', ['medications' => ['Panadol']])
            ->assertStatus(422);
    }

    // --- checkPregnancy ----------------------------------------------------

    public function test_pregnancy_safety_check_relays_a_single_drug(): void
    {
        // PREG-001-style.
        $user = $this->patientUser();

        Http::fake([
            '*/pregnancy*' => Http::response([
                'drug_a' => 'Isotretinoin',
                'risk_category' => 'X',
                'warning' => 'Contraindicated in pregnancy.',
            ], 200),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/pregnancy', ['drug_a' => 'Isotretinoin'])
            ->assertStatus(200)
            ->assertJsonPath('data.risk_category', 'X');
    }

    // --- checkConditions -----------------------------------------------------

    public function test_condition_contraindication_check_relays_correctly(): void
    {
        $user = $this->patientUser();

        Http::fake([
            '*/condition-check*' => Http::response([
                'warnings' => [['medication' => 'Ibuprofen', 'condition' => 'Chronic kidney disease', 'risk' => 'high']],
            ], 200),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/condition-check', [
                'medications' => ['Ibuprofen'],
                'conditions' => ['Chronic kidney disease'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.warnings.0.risk', 'high');
    }

    // --- resolve (drug name -> RxCUI) ---------------------------------------

    public function test_resolve_drug_returns_a_suggestion_for_a_misspelled_name(): void
    {
        // PHARM-ERROR-001-style: unresolvable/misspelled drug name.
        $user = $this->patientUser();

        Http::fake([
            '*/resolve*' => Http::response([
                'resolved' => false,
                'suggestion' => 'Paracetamol',
                'message' => 'Did you mean Paracetamol?',
            ], 200),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ddi/resolve?name=Paracetamoll')
            ->assertStatus(200)
            ->assertJsonPath('data.resolved', false)
            ->assertJsonPath('data.suggestion', 'Paracetamol');
    }

    // --- history / show: ownership -------------------------------------------

    public function test_history_only_shows_the_authenticated_users_own_checks(): void
    {
        $owner = $this->patientUser();
        $stranger = $this->patientUser();

        Http::fake(['*/interaction*' => Http::response(['prediction' => 'no_interaction'], 200)]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/ddi/interaction', ['drug_a' => 'Warfarin', 'drug_b' => 'Aspirin']);

        $response = $this->actingAs($stranger, 'sanctum')->getJson('/api/ddi/checks');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_show_a_single_check_not_owned_by_the_caller_returns_not_found(): void
    {
        $owner = $this->patientUser();
        $stranger = $this->patientUser();

        Http::fake(['*/interaction*' => Http::response(['prediction' => 'no_interaction'], 200)]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/ddi/interaction', ['drug_a' => 'Warfarin', 'drug_b' => 'Aspirin']);

        $checkId = \App\Models\DrugInteractionCheck::first()->id;

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/ddi/checks/{$checkId}")
            ->assertStatus(404);
    }

    // --- error handling --------------------------------------------------------

    public function test_ddi_service_error_degrades_to_a_json_error_not_a_raw_exception(): void
    {
        $user = $this->patientUser();

        Http::fake(['*/interaction*' => Http::response(['detail' => 'internal server error'], 500)]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/interaction', ['drug_a' => 'Warfarin', 'drug_b' => 'Aspirin']);

        $response->assertJsonPath('success', false);
        $this->assertStringNotContainsString('Exception', $response->getContent());
        $this->assertStringNotContainsString('.php', $response->getContent());
    }

    public function test_ddi_service_unreachable_returns_a_502_class_error(): void
    {
        $user = $this->patientUser();

        Http::fake(['*/interaction*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('refused')]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ddi/interaction', ['drug_a' => 'Warfarin', 'drug_b' => 'Aspirin']);

        $this->assertGreaterThanOrEqual(500, $response->status());
        $response->assertJsonPath('success', false);
    }
}
