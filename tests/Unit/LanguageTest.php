<?php

namespace Tests\Unit;

use App\Support\Language;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    public function test_every_language_has_a_name_in_every_locale(): void
    {
        foreach (['en', 'uz', 'ru'] as $locale) {
            $names = require lang_path("{$locale}/languages.php");

            $this->assertEqualsCanonicalizing(Language::CODES, array_keys($names), "lang/{$locale}/languages.php");
        }
    }

    public function test_popular_languages_are_known_codes(): void
    {
        $this->assertEmpty(array_diff(Language::POPULAR, Language::CODES));
    }

    public function test_names_follow_the_current_locale(): void
    {
        app()->setLocale('uz');
        $this->assertSame('Fransuz tili', Language::name('fr'));

        app()->setLocale('ru');
        $this->assertSame('Французский', Language::name('fr'));

        $this->assertNull(Language::name('xx'));
        $this->assertNull(Language::name(null));
    }

    public function test_options_list_popular_first_and_every_language_once(): void
    {
        $options = Language::options();

        $this->assertSame(Language::POPULAR, array_keys($options['popular']));
        $this->assertEqualsCanonicalizing(Language::CODES, [...array_keys($options['popular']), ...array_keys($options['others'])]);
    }

    public function test_picker_items_can_be_searched_in_any_interface_language(): void
    {
        app()->setLocale('uz');
        $french = collect(Language::pickerItems())->firstWhere('code', 'fr');

        $this->assertSame('Fransuz tili', $french['name']);
        $this->assertStringContainsString('french', $french['search']);
        $this->assertStringContainsString('французский', $french['search']);
        $this->assertStringContainsString('fransuz', $french['search']);
    }

    public function test_picker_lists_popular_languages_first(): void
    {
        $items = Language::pickerItems();

        $this->assertSame(Language::POPULAR, array_column(array_slice($items, 0, count(Language::POPULAR)), 'code'));
        $this->assertCount(count(Language::CODES), $items);
    }
}
