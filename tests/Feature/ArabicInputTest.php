<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Medication;
use App\Support\ArabicSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards UTF-8 Arabic handling across the request lifecycle: validation,
 * storage, retrieval, JSON output and search.
 */
class ArabicInputTest extends TestCase
{
    use RefreshDatabase;

    private const ARABIC = 'صداع شديد مع ارتفاع في درجة الحرارة';

    /* ---------------------------------------------------------------
     | Storage round-trip
     |--------------------------------------------------------------- */

    public function test_arabic_text_round_trips_through_the_database(): void
    {
        $faq = Faq::create([
            'question' => 'probe',
            'question_ar' => self::ARABIC,
            'answer' => 'probe',
            'answer_ar' => self::ARABIC,
        ]);

        $this->assertSame(self::ARABIC, Faq::find($faq->id)->question_ar);
    }

    public function test_four_byte_characters_survive_storage(): void
    {
        // Fails on 3-byte utf8; proves the column is genuinely utf8mb4.
        $withEmoji = 'ألم 😷 حاد';

        $faq = Faq::create([
            'question' => 'probe',
            'question_ar' => $withEmoji,
            'answer' => 'probe',
        ]);

        $this->assertSame($withEmoji, Faq::find($faq->id)->question_ar);
    }

    public function test_mixed_arabic_and_english_is_preserved(): void
    {
        $mixed = 'Paracetamol 500mg — باراسيتامول';

        $medication = Medication::create(['name' => $mixed, 'dosage' => '500mg']);

        $this->assertSame($mixed, Medication::find($medication->id)->name);
    }

    /* ---------------------------------------------------------------
     | Validation accepts Arabic
     |--------------------------------------------------------------- */

    public function test_arabic_names_pass_validation(): void
    {
        // A pure-Arabic name must not trip any character-class rule.
        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'محمد عبد الله',
            'email' => 'not-an-email',
            'role' => 'patient',
        ]);

        $response->assertStatus(422);

        $errors = implode(' ', (array) $response->json('errors'));
        $this->assertStringNotContainsStringIgnoringCase('full_name', $errors);
        $this->assertStringNotContainsStringIgnoringCase('full name', $errors);
    }

    public function test_string_length_limits_count_characters_not_bytes(): void
    {
        // 255 Arabic characters = 510 bytes. A byte-based max:255 would reject it.
        $validator = validator(
            ['value' => str_repeat('ص', 255)],
            ['value' => 'required|string|max:255']
        );

        $this->assertFalse($validator->fails(), 'max:255 must mean 255 characters, not bytes.');
    }

    /* ---------------------------------------------------------------
     | JSON output
     |--------------------------------------------------------------- */

    public function test_json_responses_emit_raw_utf8_and_still_decode(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/auth/login', []);

        $raw = $response->getContent();

        $this->assertStringNotContainsString('\u06', $raw, 'Arabic should not be \uXXXX-escaped.');
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertIsArray(json_decode($raw, true));
    }

    /* ---------------------------------------------------------------
     | Search
     |--------------------------------------------------------------- */

    public function test_like_wildcards_in_user_input_are_escaped(): void
    {
        $this->assertSame('\%foo\_bar', ArabicSearch::escapeLike('%foo_bar'));
    }

    public function test_arabic_is_detected(): void
    {
        $this->assertTrue(ArabicSearch::hasArabic('دمشق'));
        $this->assertTrue(ArabicSearch::hasArabic('Damascus دمشق'));
        $this->assertFalse(ArabicSearch::hasArabic('Damascus'));
    }

    /**
     * The core Arabic search problem: users type plain alef/heh, the stored
     * value carries hamza/teh-marbuta, and no _ci collation folds them.
     *
     * @dataProvider arabicVariantProvider
     */
    public function test_arabic_letter_variants_match(string $needle, string $haystack): void
    {
        $matched = \DB::selectOne(
            'SELECT (? REGEXP ?) AS m',
            [$haystack, ArabicSearch::toRegex($needle)]
        );

        $this->assertTrue((bool) $matched->m, "\"$needle\" should match \"$haystack\"");
    }

    public static function arabicVariantProvider(): array
    {
        return [
            'alef hamza' => ['احمد', 'أحمد'],
            'alef madda' => ['امال', 'آمال'],
            'teh marbuta' => ['فاطمه', 'فاطمة'],
            'alef maksura' => ['على', 'علي'],
            'waw hamza' => ['مومن', 'مؤمن'],
            'exact match' => ['دمشق', 'دمشق'],
            'substring' => ['حمرا', 'شارع الحمراء'],
        ];
    }

    public function test_search_does_not_match_unrelated_text(): void
    {
        $matched = \DB::selectOne(
            'SELECT (? REGEXP ?) AS m',
            ['شارع بغداد', ArabicSearch::toRegex('دمشق')]
        );

        $this->assertFalse((bool) $matched->m, 'Folding must not make everything match.');
    }

    public function test_english_search_still_uses_plain_like(): void
    {
        Faq::create(['question' => 'Damascus clinic', 'answer' => 'x']);

        $found = ArabicSearch::apply(Faq::query(), ['question'], 'Damascus')->count();
        $notFound = ArabicSearch::apply(Faq::query(), ['question'], 'Aleppo')->count();

        $this->assertSame(1, $found);
        $this->assertSame(0, $notFound);
    }

    public function test_blank_search_term_is_a_no_op(): void
    {
        Faq::create(['question' => 'a', 'answer' => 'x']);
        Faq::create(['question' => 'b', 'answer' => 'x']);

        $this->assertSame(2, ArabicSearch::apply(Faq::query(), ['question'], '   ')->count());
        $this->assertSame(2, ArabicSearch::apply(Faq::query(), ['question'], null)->count());
    }
}
