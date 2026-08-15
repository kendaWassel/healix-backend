<?php

namespace App\Support;

/**
 * Maps Python urgency levels (EMERGENCY | URGENT | SEMI_URGENT | NON_URGENT)
 * to the legacy assessments.triage enum (High | Medium | Low).
 */
final class UrgencyTriageMapper
{
    public static function toLegacy(string $urgencyLevel): string
    {
        return match (strtoupper($urgencyLevel)) {
            'EMERGENCY', 'URGENT' => 'High',
            'SEMI_URGENT' => 'Medium',
            default => 'Low',
        };
    }
}
