<?php

namespace Tests\Unit;

use App\Models\Specialization;
use App\Services\SpecializationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecializationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_exact_match_against_arabic_name(): void
    {
        $specialization = Specialization::create([
            'name' => 'Cardiology', 'name_ar' => 'أمراض القلب', 'code' => 'cardiology',
        ]);

        $resolved = (new SpecializationResolver())->resolve('أمراض القلب');

        $this->assertNotNull($resolved);
        $this->assertSame($specialization->id, $resolved->id);
    }

    public function test_no_longer_matches_the_english_name(): void
    {
        // Healix (the current, sole AI backend) sends the Arabic name_ar
        // value, never the English name — an English string here is exactly
        // the legacy pipeline's shape, which no longer calls this resolver.
        Specialization::create(['name' => 'Cardiology', 'name_ar' => 'أمراض القلب', 'code' => 'cardiology']);

        $resolved = (new SpecializationResolver())->resolve('Cardiology');

        $this->assertNull($resolved);
    }

    public function test_returns_null_when_no_specialization_matches(): void
    {
        Specialization::create(['name' => 'Cardiology', 'name_ar' => 'أمراض القلب', 'code' => 'cardiology']);

        $resolved = (new SpecializationResolver())->resolve('تخصص غير موجود');

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
