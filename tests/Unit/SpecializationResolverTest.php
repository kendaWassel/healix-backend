<?php

namespace Tests\Unit;

use App\Models\Specialization;
use App\Services\SpecializationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecializationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_exact_case_insensitive_match(): void
    {
        $specialization = Specialization::create([
            'name' => 'Cardiology', 'name_ar' => 'أمراض القلب', 'code' => 'cardiology',
        ]);

        $resolved = (new SpecializationResolver())->resolve('cardiology');

        $this->assertNotNull($resolved);
        $this->assertSame($specialization->id, $resolved->id);
    }

    public function test_returns_null_when_no_specialization_matches(): void
    {
        Specialization::create(['name' => 'Cardiology', 'name_ar' => 'أمراض القلب', 'code' => 'cardiology']);

        $resolved = (new SpecializationResolver())->resolve('Nonexistent Specialty XYZ');

        $this->assertNull($resolved);
    }

    public function test_returns_null_for_empty_or_null_input(): void
    {
        $resolver = new SpecializationResolver();

        $this->assertNull($resolver->resolve(''));
        $this->assertNull($resolver->resolve(null));
        $this->assertNull($resolver->resolve('   '));
    }
}
