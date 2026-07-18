<?php

namespace App\Services\DDI;

use App\Models\DrugInteractionCheck;
use App\Models\User;

/**
 * Persists a DDI check result into the drug_interaction_checks history table.
 *
 * Kept separate from DdiService (which stays a pure HTTP client) so persistence
 * is a single, testable responsibility. Each check type exposes its severity /
 * count differently, so this class normalises them into two summary columns.
 */
class DrugInteractionCheckRecorder
{
    /** Ordering used to pick the worst severity across a set of findings. */
    protected const SEVERITY_RANK = [
        'Major' => 3,
        'Moderate' => 2,
        'Minor' => 1,
    ];

    /**
     * @param  array<string, mixed>  $input   The validated request payload.
     * @param  array<string, mixed>  $result  The raw DDI service response.
     */
    public function record(User $user, string $checkType, array $input, array $result): DrugInteractionCheck
    {
        return DrugInteractionCheck::create([
            'user_id' => $user->id,
            'patient_id' => $user->patient?->id,
            'check_type' => $checkType,
            'highest_severity' => $this->highestSeverity($checkType, $result),
            'interactions_found' => $this->interactionsFound($checkType, $result),
            'input' => $input,
            'result' => $result,
        ]);
    }

    protected function highestSeverity(string $checkType, array $result): ?string
    {
        return match ($checkType) {
            DrugInteractionCheck::TYPE_INTERACTION => $result['severity'] ?? null,
            DrugInteractionCheck::TYPE_BATCH => $this->worstSeverity(
                array_column($result['results'] ?? [], 'severity')
            ),
            DrugInteractionCheck::TYPE_SCREEN => $this->worstSeverity(
                array_column($result['findings'] ?? [], 'severity')
            ),
            // Pregnancy uses a category (X/D/C/...) for two drugs or one.
            DrugInteractionCheck::TYPE_PREGNANCY => $result['overall_risk'] ?? $result['category'] ?? null,
            default => null,
        };
    }

    protected function interactionsFound(string $checkType, array $result): int
    {
        return match ($checkType) {
            DrugInteractionCheck::TYPE_INTERACTION => ($result['prediction'] ?? null) === 'Interaction likely' ? 1 : 0,
            DrugInteractionCheck::TYPE_BATCH => count(array_filter(
                $result['results'] ?? [],
                fn ($r) => ($r['prediction'] ?? null) === 'Interaction likely'
            )),
            DrugInteractionCheck::TYPE_SCREEN => (int) ($result['interactions_found'] ?? count($result['findings'] ?? [])),
            DrugInteractionCheck::TYPE_ALLERGY => count($result['cross_reactive_drugs'] ?? []),
            default => 0,
        };
    }

    /**
     * @param  array<int, string|null>  $severities
     */
    protected function worstSeverity(array $severities): ?string
    {
        $worst = null;
        $worstRank = 0;

        foreach ($severities as $severity) {
            $rank = self::SEVERITY_RANK[$severity] ?? 0;
            if ($rank > $worstRank) {
                $worstRank = $rank;
                $worst = $severity;
            }
        }

        return $worst;
    }
}
