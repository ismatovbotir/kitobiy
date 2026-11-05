<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Folds a name or title into a plain search key, so different ways of writing
 * the same word meet: "Choʻlpon", "Cho'lpon", "CHOLPON" and "Чўлпон" all
 * become "cholpon", and "Есенин" and "Yesenin" both become "esenin".
 *
 * Cyrillic follows the Uzbek Latin alphabet (х → x, ч → ch, ц → ts), so
 * Uzbek Latin and Cyrillic match exactly, and Russian names match their
 * Uzbek Latin spelling ("Достоевский" = "Dostoyevskiy"). English spellings
 * that differ ("Dostoevsky") still match on a shorter part ("dostoevsk").
 *
 * Changing these rules needs `php artisan kitobiy:rebuild-search-keys`.
 */
class SearchKey
{
    /**
     * Uzbek and Russian Cyrillic letters (lowercase) in Uzbek Latin.
     *
     * @var array<string, string>
     */
    private const array CYRILLIC = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'ғ' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y',
        'к' => 'k', 'қ' => 'q', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'ў' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'x', 'ҳ' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh',
        'щ' => 'sh', 'ъ' => '', 'ы' => 'i', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya', 'і' => 'i', 'ї' => 'yi', 'є' => 'e', 'ґ' => 'g', 'ң' => 'n',
        'ү' => 'u', 'ұ' => 'u', 'ө' => 'o', 'ә' => 'a', 'һ' => 'h',
    ];

    /**
     * Apostrophes and quote marks, dropped: oʻ/o‘/o'/o` → o.
     */
    private const string APOSTROPHES = '/[\'"`´ʻʼʹʽ‘’‚‛“”„«»]/u';

    public static function from(?string $text): string
    {
        $key = mb_strtolower(trim((string) $text));

        if ($key === '') {
            return '';
        }

        $key = preg_replace(self::APOSTROPHES, '', $key);
        $key = strtr($key, self::CYRILLIC);
        $key = Str::ascii($key);
        $key = str_replace('ye', 'e', $key);

        return trim(preg_replace('/\s+/', ' ', $key));
    }

    /**
     * A LIKE pattern for "contains the term", or null when the term folds to nothing.
     */
    public static function containsPattern(?string $term): ?string
    {
        $key = self::from($term);

        return $key === '' ? null : '%'.addcslashes($key, '%_\\').'%';
    }
}
