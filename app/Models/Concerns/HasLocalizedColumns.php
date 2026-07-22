<?php

namespace App\Models\Concerns;

use App\Support\Locale;

/**
 * Serves a bilingual column pair for the current request locale.
 *
 * Models opt in by listing their translatable fields:
 *
 *     use HasLocalizedColumns;
 *     protected array $localizable = ['name'];
 *
 * Storage convention
 * ------------------
 * The ORIGINAL column (e.g. `name`) stays the canonical English value and the
 * stable lookup key. Only one extra column per field is added (`name_ar`), so
 * nothing is duplicated. An optional `name_en` column is honoured too, for
 * tables that prefer a fully symmetric pair.
 *
 * Reading `$model->name` returns the value for the active locale, falling back
 * to English whenever the Arabic column is still empty — a partially translated
 * row therefore never renders blank. `$model->name_ar` / `$model->name_en`
 * remain directly reachable for admin screens and for lookups that must not
 * shift with the locale.
 */
trait HasLocalizedColumns
{
    /**
     * Value of $field for the current (or given) locale, with English fallback.
     */
    public function localized(string $field, ?string $locale = null): ?string
    {
        $locale ??= Locale::current();

        $value = $this->rawLocalized("{$field}_{$locale}");

        if ($value !== null) {
            return $value;
        }

        return $this->rawLocalized("{$field}_en")
            ?? $this->rawLocalized($field);
    }

    /**
     * Both translations of $field, for admin screens and content editors.
     *
     * @return array{en: string|null, ar: string|null}
     */
    public function translations(string $field): array
    {
        return [
            'en' => $this->rawLocalized("{$field}_en") ?? $this->rawLocalized($field),
            'ar' => $this->rawLocalized("{$field}_ar"),
        ];
    }

    /**
     * Fields the model declared translatable.
     *
     * @return array<int, string>
     */
    public function localizableFields(): array
    {
        return property_exists($this, 'localizable') ? $this->localizable : [];
    }

    /**
     * Resolve reads of a declared field to the localized value.
     *
     * Guarded by hasTranslationColumns() so the model still behaves normally
     * before the migration runs, or when the row was loaded with a partial
     * select() that omitted the translation columns.
     */
    public function getAttribute($key)
    {
        if (
            is_string($key)
            && in_array($key, $this->localizableFields(), true)
            && $this->hasTranslationColumns($key)
        ) {
            return $this->localized($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * True when at least one translation column for $field was loaded.
     */
    protected function hasTranslationColumns(string $field): bool
    {
        return array_key_exists("{$field}_ar", $this->attributes)
            || array_key_exists("{$field}_en", $this->attributes);
    }

    /**
     * Read straight from the loaded attributes, bypassing getAttribute() so the
     * override above can never recurse into itself.
     */
    protected function rawLocalized(string $column): ?string
    {
        $value = $this->attributes[$column] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
