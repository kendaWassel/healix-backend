<?php

namespace Tests\Feature;

use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the locale negotiation contract the mobile/web clients rely on:
 * Accept-Language in, translated copy out, English by default, and no change
 * to any existing JSON key or response shape.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------------
     | Locale negotiation
     |--------------------------------------------------------------- */

    public function test_defaults_to_english_when_no_header_is_sent(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertHeader('Content-Language', 'en');
    }

    public function test_arabic_header_selects_arabic(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/auth/login', []);

        $response->assertHeader('Content-Language', 'ar');
    }

    public function test_region_subtags_are_reduced_to_the_base_language(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'ar-SA'])
            ->postJson('/api/auth/login', []);

        $response->assertHeader('Content-Language', 'ar');
    }

    public function test_quality_values_are_honoured(): void
    {
        // en carries the higher q-value even though ar is listed first.
        $response = $this->withHeaders(['Accept-Language' => 'ar;q=0.3,en;q=0.9'])
            ->postJson('/api/auth/login', []);

        $response->assertHeader('Content-Language', 'en');
    }

    public function test_unsupported_locale_falls_back_to_default(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'fr-FR,de;q=0.8'])
            ->postJson('/api/auth/login', []);

        $response->assertHeader('Content-Language', 'en');
    }

    public function test_query_parameter_overrides_the_header(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/faqs?lang=ar');

        $response->assertHeader('Content-Language', 'ar');
    }

    public function test_response_varies_on_accept_language(): void
    {
        $this->getJson('/api/faqs')
            ->assertHeader('Vary', 'Accept-Language');
    }

    /* ---------------------------------------------------------------
     | Translated payloads
     |--------------------------------------------------------------- */

    public function test_validation_errors_are_translated(): void
    {
        $english = $this->postJson('/api/auth/login', [])->json('errors');

        $arabic = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/auth/login', [])
            ->json('errors');

        $this->assertNotEmpty($english);
        $this->assertNotEmpty($arabic);
        $this->assertNotSame($english, $arabic, 'Validation errors did not change with the locale.');
    }

    public function test_response_structure_is_identical_across_locales(): void
    {
        $english = $this->postJson('/api/auth/login', ['email' => 'x', 'password' => 'y']);
        $arabic = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/auth/login', ['email' => 'x', 'password' => 'y']);

        $this->assertSame($english->status(), $arabic->status());
        $this->assertSame(
            array_keys($english->json()),
            array_keys($arabic->json()),
            'Localization must never add, remove or rename a JSON key.'
        );
    }

    /* ---------------------------------------------------------------
     | Translation files
     |--------------------------------------------------------------- */

    public function test_every_english_file_has_an_arabic_counterpart_with_the_same_keys(): void
    {
        $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($items as $key => $value) {
                $path = $prefix === '' ? (string) $key : "$prefix.$key";
                $keys = is_array($value)
                    ? array_merge($keys, $flatten($value, $path))
                    : array_merge($keys, [$path]);
            }
            return $keys;
        };

        foreach (glob(lang_path('en/*.php')) as $englishFile) {
            $name = basename($englishFile);
            $arabicFile = lang_path("ar/$name");

            $this->assertFileExists($arabicFile, "Missing Arabic translation file: $name");

            $missing = array_diff($flatten(require $englishFile), $flatten(require $arabicFile));

            $this->assertEmpty(
                $missing,
                "ar/$name is missing keys: " . implode(', ', $missing)
            );
        }
    }

    /* ---------------------------------------------------------------
     | Locale helper
     |--------------------------------------------------------------- */

    public function test_enum_labels_translate_and_fall_back_safely(): void
    {
        app()->setLocale('en');
        $this->assertSame('Delivered', Locale::label('order_status', 'delivered'));

        app()->setLocale('ar');
        $this->assertSame('تم التسليم', Locale::label('order_status', 'delivered'));

        // An enum value with no translation must return the raw value, never null.
        $this->assertSame('brand_new_status', Locale::label('order_status', 'brand_new_status'));
        $this->assertNull(Locale::label('order_status', null));
    }

    public function test_direction_follows_the_locale(): void
    {
        app()->setLocale('en');
        $this->assertSame('ltr', Locale::direction());
        $this->assertFalse(Locale::isRtl());

        app()->setLocale('ar');
        $this->assertSame('rtl', Locale::direction());
        $this->assertTrue(Locale::isRtl());
    }
}
