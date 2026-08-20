<?php

namespace App\Services;

use App\Models\Specialization;

/**
 * Resolves the specialty string Healix (the AI service) returns to a real
 * Specialization row, instead of every consumer doing its own ad-hoc string
 * matching.
 *
 * Matches against `name_ar`, NOT `name`. Healix's own nodes/route_specialty.py
 * (Python side) already translates its knowledge base's specialty strings
 * into one of Laravel's real specializations.name_ar values via its own
 * SPECIALTY_MAP — or, when nothing maps, a free-text referral phrase with no
 * matching row at all (GENERAL_REFERRAL_PHRASE) — specifically so this
 * resolver receives an Arabic string, not an English one.
 *
 * Previously matched against the English `name` column, for the legacy
 * MedicalAssistantService pipeline's recommended_specialty (an English
 * string like "Cardiology"). That pipeline is retired and nothing else
 * calls this method with an English name — see HealixConversationService,
 * the only caller.
 *
 * Case-insensitive exact string match. Not every AI-recommended specialty
 * has a matching row (no doctors exist for it, or it's the free-text
 * referral fallback) — that's a data/ops gap, not something this resolver
 * should paper over by guessing a "close enough" specialty.
 */
class SpecializationResolver
{
    public function resolve(?string $recommendedSpecialty): ?Specialization
    {
        $name = trim((string) $recommendedSpecialty);
        if ($name === '') {
            return null;
        }

        return Specialization::whereRaw('LOWER(name_ar) = ?', [strtolower($name)])->first();
    }
}
