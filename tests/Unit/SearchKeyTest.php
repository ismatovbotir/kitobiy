<?php

namespace Tests\Unit;

use App\Support\SearchKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SearchKeyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function sameWords(): array
    {
        return [
            'uz turned comma' => ['Choʻlpon', 'cholpon'],
            'uz right quote' => ['Cho’lpon', 'cholpon'],
            'plain apostrophe' => ["Cho'lpon", 'cholpon'],
            'backtick' => ['Cho`lpon', 'cholpon'],
            'uz Cyrillic' => ['Чўлпон', 'cholpon'],
            'uz Cyrillic letters' => ['Ғафур Ғулом', 'gafur gulom'],
            'qodiriy Cyrillic' => ['Абдулла Қодирий', 'abdulla qodiriy'],
            'qodiriy Latin' => ['ABDULLA QODIRIY', 'abdulla qodiriy'],
            'ye at the start' => ['Yesenin', 'esenin'],
            'е in Cyrillic' => ['Есенин', 'esenin'],
            'russian name' => ['Достоевский', 'dostoevskiy'],
            'uz Latin spelling of it' => ['Dostoyevskiy', 'dostoevskiy'],
            'soft sign dropped' => ['Гоголь', 'gogol'],
            'accents dropped' => ['Café  Noël', 'cafe noel'],
            'title quotes' => ['«O‘tkan kunlar»', 'otkan kunlar'],
        ];
    }

    #[DataProvider('sameWords')]
    public function test_folds_spellings_together(string $text, string $key): void
    {
        $this->assertSame($key, SearchKey::from($text));
    }

    public function test_blank_text_gives_an_empty_key(): void
    {
        $this->assertSame('', SearchKey::from(null));
        $this->assertSame('', SearchKey::from('   '));
        $this->assertSame('', SearchKey::from("''"));
    }

    public function test_contains_pattern_escapes_like_wildcards(): void
    {
        $this->assertSame('%100\\% sof%', SearchKey::containsPattern('100% sof'));
        $this->assertNull(SearchKey::containsPattern('ʻ'));
    }
}
