<?php

namespace Tests\Unit;

use App\Support\Isbn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IsbnTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function validIsbns(): array
    {
        return [
            'isbn-10' => ['0306406152', '9780306406157', '0306406152'],
            'isbn-10 with X check digit' => ['0-8044-2957-x', '9780804429573', '080442957X'],
            'hyphenated isbn-13' => ['978-0-306-40615-7', '9780306406157', '0306406152'],
            '979 isbn-13 has no isbn-10' => ['979-10-90636-07-1', '9791090636071', null],
        ];
    }

    #[DataProvider('validIsbns')]
    public function test_parse_returns_both_forms(string $input, string $isbn13, ?string $isbn10): void
    {
        $this->assertSame(['isbn13' => $isbn13, 'isbn10' => $isbn10], Isbn::parse($input));
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function invalidIsbns(): array
    {
        return [
            'wrong isbn-13 check digit' => ['9780306406158'],
            'wrong isbn-10 check digit' => ['0306406153'],
            'too short' => ['12345'],
            'letters' => ['abcdefghij'],
            'non-book ean' => ['4006381333931'],
            'empty' => [''],
            'null' => [null],
        ];
    }

    #[DataProvider('invalidIsbns')]
    public function test_parse_rejects_invalid_input(?string $input): void
    {
        $this->assertNull(Isbn::parse($input));
    }
}
