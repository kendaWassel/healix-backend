<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Locale-aware "contains" search for user-supplied text.
 *
 * Two problems this solves, both reproducible against the current schema:
 *
 *  1. LIKE wildcard injection. `%` and `_` typed by a user were passed straight
 *     into the pattern, so searching for "%" matched every row.
 *
 *  2. Arabic orthographic variants. Under utf8mb4_unicode_ci, "احمد" does NOT
 *     match "أحمد" and "فاطمه" does NOT match "فاطمة" — the hamza and
 *     teh-marbuta forms are distinct Unicode characters, not accents, so no
 *     _ci / _ai collation folds them. Arabic speakers routinely type the plain
 *     forms, so this made Arabic search unusable in practice.
 *
 * ASCII-only input keeps using a plain LIKE (unchanged behaviour, no regex
 * cost). Arabic input is compiled into a REGEXP with folded character classes.
 * Neither form can use an index for a leading-wildcard match, so the regex path
 * costs no extra index opportunity — it is the same full scan either way.
 *
 * This is orthography folding for search only. It does NOT normalize drug names
 * or symptoms, and nothing here is persisted.
 */
class ArabicSearch
{
    /**
     * Interchangeable Arabic letter forms, keyed by the character class that
     * should replace any member of the group.
     */
    private const EQUIVALENCE_GROUPS = [
        '[اأإآٱ]' => ['ا', 'أ', 'إ', 'آ', 'ٱ'],   // alef forms
        '[يىئ]' => ['ي', 'ى', 'ئ'],               // yeh / alef maksura / hamza on yeh
        '[ةه]' => ['ة', 'ه'],                      // teh marbuta / heh
        '[وؤ]' => ['و', 'ؤ'],                      // waw / hamza on waw
    ];

    /**
     * Tashkeel (harakat) plus tatweel. Users almost never type these, but the
     * stored text may contain them, so they are stripped from the needle and
     * allowed to appear anywhere in the haystack.
     */
    private const DIACRITICS = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0640}\x{0670}\x{06D6}-\x{06ED}]/u';

    /**
     * Apply a "contains" filter across one or more columns.
     *
     * @param  array<int, string>  $columns
     */
    public static function apply(Builder $query, array $columns, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($columns, $term) {
            foreach ($columns as $column) {
                self::containsAny($inner, $column, $term);
            }
        });
    }

    /**
     * OR a single column into an existing constraint group.
     */
    private static function containsAny(Builder $query, string $column, string $term): void
    {
        if (self::hasArabic($term)) {
            $query->orWhereRaw("{$column} REGEXP ?", [self::toRegex($term)]);

            return;
        }

        $query->orWhere($column, 'LIKE', '%' . self::escapeLike($term) . '%');
    }

    /**
     * Neutralize LIKE metacharacters so user input cannot alter the pattern.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    public static function hasArabic(string $value): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $value);
    }

    /**
     * Compile a search term into a REGEXP that tolerates Arabic letter variants
     * and stray diacritics.
     */
    public static function toRegex(string $term): string
    {
        $term = preg_replace(self::DIACRITICS, '', $term) ?? $term;

        $out = '';

        foreach (preg_split('//u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $out .= self::classFor($char);
            // Tolerate diacritics sitting between letters in the stored value.
            $out .= '[\x{064B}-\x{0652}\x{0640}]*';
        }

        return $out;
    }

    /**
     * Character class covering every form of $char, or the escaped literal.
     */
    private static function classFor(string $char): string
    {
        foreach (self::EQUIVALENCE_GROUPS as $class => $members) {
            if (in_array($char, $members, true)) {
                return $class;
            }
        }

        // Escape regex metacharacters in whatever the user typed.
        return preg_quote($char, '/');
    }
}
