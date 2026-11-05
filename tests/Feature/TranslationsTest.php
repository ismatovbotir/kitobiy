<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TranslationsTest extends TestCase
{
    /**
     * Every __('...') string in our views must have a uz and ru translation,
     * so a missing key fails CI instead of showing English.
     */
    public function test_every_view_string_is_translated_to_uzbek_and_russian(): void
    {
        $keys = collect(File::allFiles(resource_path('views')))
            ->flatMap(function ($file): array {
                preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)+)'/", $file->getContents(), $matches);

                return $matches[1];
            })
            ->map(fn (string $key): string => stripslashes($key))
            ->reject(fn (string $key): bool => preg_match('/^[a-z_]+\.[a-z_.]+$/', $key) === 1)
            ->unique();

        $this->assertNotEmpty($keys);

        foreach (['uz', 'ru'] as $locale) {
            $translations = json_decode(File::get(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $missing = $keys->reject(fn (string $key): bool => array_key_exists($key, $translations));

            $this->assertEmpty($missing->all(), "Missing {$locale} translations: ".$missing->implode(', '));
        }
    }

    public function test_uzbek_and_russian_files_have_the_same_keys(): void
    {
        $uz = json_decode(File::get(lang_path('uz.json')), true, flags: JSON_THROW_ON_ERROR);
        $ru = json_decode(File::get(lang_path('ru.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertEqualsCanonicalizing(array_keys($uz), array_keys($ru));
    }
}
