<?php

namespace App\Services;

use App\Models\Specialization;

/**
 * Resolves the free-text specialty string the AI service returns
 * (Assessment::recommended_specialty) to a real Specialization row, instead
 * of every consumer doing its own ad-hoc string matching.
 *
 * Match is case-insensitive exact string (Specialization::name is documented
 * as "the stable lookup key ... used by the AI service's recommended_specialty").
 * Not every AI-recommended specialty has a matching row yet (no doctors exist
 * for it) — that's a data/ops gap, not something this resolver should paper
 * over by guessing a "close enough" specialty.
 */
class SpecializationResolver
{
    public function resolve(?string $recommendedSpecialty): ?Specialization
    {
        $name = trim((string) $recommendedSpecialty);
        if ($name === '') {
            return null;
        }

        return Specialization::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }
}
