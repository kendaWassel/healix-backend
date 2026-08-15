<?php

namespace Tests\Unit;

use App\Support\UrgencyTriageMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UrgencyTriageMapperTest extends TestCase
{
    #[DataProvider('urgencyLevels')]
    public function test_maps_python_urgency_to_legacy_triage(string $pythonLevel, string $legacyTriage): void
    {
        $this->assertSame($legacyTriage, UrgencyTriageMapper::toLegacy($pythonLevel));
    }

    public static function urgencyLevels(): array
    {
        return [
            ['EMERGENCY', 'High'],
            ['URGENT', 'High'],
            ['SEMI_URGENT', 'Medium'],
            ['NON_URGENT', 'Low'],
            ['unknown', 'Low'],
        ];
    }
}
