<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * Single place for locale-derived display helpers.
 *
 * Exists so controllers and API Resources never repeat the
 * "translate this enum, fall back to the raw value" dance.
 */
class Locale
{
    /**
     * Human-readable label for a stored enum value.
     *
     * The raw value is returned untouched when no translation exists, so an
     * enum gaining a new case never produces an empty label or an exception.
     *
     * @param  string  $group  Key under lang/{locale}/enums.php (e.g. 'order_status')
     */
    public static function label(string $group, ?string $value, ?string $fallback = null): ?string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $key = "enums.{$group}.{$value}";

        return Lang::has($key) ? __($key) : ($fallback ?? $value);
    }

    /**
     * Render a translation key in every supported locale at once.
     *
     * Used where stored content (e.g. notification `data` payloads) needs to
     * carry every language up front rather than committing to whichever
     * locale happened to be active at write time — the reader picks which
     * key to display.
     *
     * @param  string  $key  Full lang key, e.g. 'notification.prescription_accepted_title'
     * @param  array<string, mixed>  $replace
     * @return array<string, string>  Keyed by locale, e.g. ['en' => '...', 'ar' => '...']
     */
    public static function translateAll(string $key, array $replace = []): array
    {
        $supported = (array) config('localization.supported', ['en']);

        $result = [];
        foreach ($supported as $locale) {
            $result[$locale] = Lang::get($key, $replace, $locale);
        }

        return $result;
    }

    /**
     * Current locale, guaranteed to be one of the supported locales.
     */
    public static function current(): string
    {
        $locale = app()->getLocale();
        $supported = (array) config('localization.supported', ['en']);

        return in_array($locale, $supported, true)
            ? $locale
            : (string) config('localization.default', 'en');
    }

    /**
     * Text direction for the current locale: 'ltr' or 'rtl'.
     */
    public static function direction(?string $locale = null): string
    {
        $locale ??= self::current();

        return (string) (config("localization.direction.{$locale}") ?? 'ltr');
    }

    public static function isRtl(?string $locale = null): bool
    {
        return self::direction($locale) === 'rtl';
    }

    /**
     * Column suffix used by bilingual database columns for the current locale.
     */
    public static function columnSuffix(?string $locale = null): string
    {
        return '_' . ($locale ?? self::current());
    }
}
