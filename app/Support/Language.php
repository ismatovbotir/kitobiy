<?php

namespace App\Support;

/**
 * Book languages: ISO 639-1 codes plus "kaa" (Karakalpak, ISO 639-3).
 * Names live in lang/{en,uz,ru}/languages.php.
 */
class Language
{
    /** @var list<string> */
    public const array CODES = [
        'aa', 'ab', 'ae', 'af', 'ak', 'am', 'an', 'ar', 'as', 'av', 'ay', 'az', 'ba', 'be', 'bg', 'bi', 'bm', 'bn', 'bo', 'br',
        'bs', 'ca', 'ce', 'ch', 'co', 'cr', 'cs', 'cu', 'cv', 'cy', 'da', 'de', 'dv', 'dz', 'ee', 'el', 'en', 'eo', 'es', 'et',
        'eu', 'fa', 'ff', 'fi', 'fj', 'fo', 'fr', 'fy', 'ga', 'gd', 'gl', 'gn', 'gu', 'gv', 'ha', 'he', 'hi', 'ho', 'hr', 'ht',
        'hu', 'hy', 'hz', 'ia', 'id', 'ie', 'ig', 'ii', 'ik', 'io', 'is', 'it', 'iu', 'ja', 'jv', 'ka', 'kaa', 'kg', 'ki', 'kj',
        'kk', 'kl', 'km', 'kn', 'ko', 'kr', 'ks', 'ku', 'kv', 'kw', 'ky', 'la', 'lb', 'lg', 'li', 'ln', 'lo', 'lt', 'lu', 'lv',
        'mg', 'mh', 'mi', 'mk', 'ml', 'mn', 'mr', 'ms', 'mt', 'my', 'na', 'nb', 'nd', 'ne', 'ng', 'nl', 'nn', 'no', 'nr', 'nv',
        'ny', 'oc', 'oj', 'om', 'or', 'os', 'pa', 'pi', 'pl', 'ps', 'pt', 'qu', 'rm', 'rn', 'ro', 'ru', 'rw', 'sa', 'sc', 'sd',
        'se', 'sg', 'si', 'sk', 'sl', 'sm', 'sn', 'so', 'sq', 'sr', 'ss', 'st', 'su', 'sv', 'sw', 'ta', 'te', 'tg', 'th', 'ti',
        'tk', 'tl', 'tn', 'to', 'tr', 'ts', 'tt', 'tw', 'ty', 'ug', 'uk', 'ur', 'uz', 've', 'vi', 'vo', 'wa', 'wo', 'xh', 'yi',
        'yo', 'za', 'zh', 'zu',
    ];

    /**
     * Shown first in pickers, in this order.
     *
     * @var list<string>
     */
    public const array POPULAR = ['uz', 'ru', 'en', 'kaa', 'tr', 'ar', 'fa', 'tg', 'kk', 'ky', 'tk', 'de', 'fr'];

    public static function isValid(?string $code): bool
    {
        return in_array($code, self::CODES, true);
    }

    /**
     * The language name in the current locale, or null for an unknown code.
     */
    public static function name(?string $code): ?string
    {
        return self::isValid($code) ? __("languages.{$code}") : null;
    }

    /**
     * Picker options in the current locale: the popular languages first, then all others A–Z.
     *
     * @return array{popular: array<string, string>, others: array<string, string>}
     */
    public static function options(): array
    {
        $popular = [];

        foreach (self::POPULAR as $code) {
            $popular[$code] = self::name($code);
        }

        return [
            'popular' => $popular,
            'others' => self::sortedNames(array_diff(self::CODES, self::POPULAR)),
        ];
    }

    /**
     * Data for the searchable language picker: popular languages first, then the rest A–Z.
     * `search` holds the code and the names in every interface language, so "fra",
     * "фра" and "fr" all find French whatever the current locale is.
     *
     * @return list<array{code: string, name: string, popular: bool, search: string}>
     */
    public static function pickerItems(): array
    {
        $namesByLocale = [];

        foreach (['en', 'uz', 'ru'] as $locale) {
            $namesByLocale[$locale] = trans('languages', [], $locale);
        }

        $options = self::options();
        $items = [];

        foreach (['popular' => true, 'others' => false] as $group => $isPopular) {
            foreach ($options[$group] as $code => $name) {
                $searchTerms = array_map(fn (array $names): string => $names[$code] ?? '', $namesByLocale);

                $items[] = [
                    'code' => $code,
                    'name' => $name,
                    'popular' => $isPopular,
                    'search' => mb_strtolower($code.' '.implode(' ', $searchTerms)),
                ];
            }
        }

        return $items;
    }

    /**
     * @param  iterable<string>  $codes
     * @return array<string, string> code => name, sorted by name in the current locale
     */
    public static function sortedNames(iterable $codes): array
    {
        $names = [];

        foreach ($codes as $code) {
            if (self::isValid($code)) {
                $names[$code] = self::name($code);
            }
        }

        if (class_exists(\Collator::class)) {
            (new \Collator(app()->getLocale()))->asort($names);
        } else {
            uasort($names, fn (string $a, string $b): int => strcmp(mb_strtolower($a), mb_strtolower($b)));
        }

        return $names;
    }
}
