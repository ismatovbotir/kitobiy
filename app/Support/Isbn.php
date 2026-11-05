<?php

namespace App\Support;

use App\Models\Edition;

/**
 * ISBN checksum validation and ISBN-10 <-> ISBN-13 conversion.
 */
class Isbn
{
    /**
     * Parse any ISBN-10 or ISBN-13 (hyphens, spaces allowed). Returns both forms,
     * or null when the input is not a valid ISBN. `isbn10` is null for 979-
     * prefixed ISBNs, which have no ISBN-10 form.
     *
     * @return array{isbn13: string, isbn10: string|null}|null
     */
    public static function parse(?string $input): ?array
    {
        $isbn = Edition::normalizeIsbn($input);

        if ($isbn !== null && self::isValidIsbn10($isbn)) {
            return ['isbn13' => self::toIsbn13($isbn), 'isbn10' => $isbn];
        }

        if ($isbn !== null && self::isValidIsbn13($isbn)) {
            return ['isbn13' => $isbn, 'isbn10' => self::toIsbn10($isbn)];
        }

        return null;
    }

    public static function isValidIsbn10(string $isbn): bool
    {
        if (preg_match('/^\d{9}[\dX]$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;

        foreach (str_split($isbn) as $index => $character) {
            $sum += ($character === 'X' ? 10 : (int) $character) * (10 - $index);
        }

        return $sum % 11 === 0;
    }

    public static function isValidIsbn13(string $isbn): bool
    {
        if (preg_match('/^97[89]\d{10}$/', $isbn) !== 1) {
            return false;
        }

        return self::isbn13CheckDigit(substr($isbn, 0, 12)) === $isbn[12];
    }

    /**
     * Convert a valid ISBN-10 to ISBN-13 (978 prefix).
     */
    public static function toIsbn13(string $isbn10): string
    {
        $body = '978'.substr($isbn10, 0, 9);

        return $body.self::isbn13CheckDigit($body);
    }

    /**
     * Convert a valid ISBN-13 to ISBN-10, or null for the 979 range.
     */
    public static function toIsbn10(string $isbn13): ?string
    {
        if (! str_starts_with($isbn13, '978')) {
            return null;
        }

        $body = substr($isbn13, 3, 9);
        $sum = 0;

        foreach (str_split($body) as $index => $digit) {
            $sum += (int) $digit * (10 - $index);
        }

        $check = (11 - $sum % 11) % 11;

        return $body.($check === 10 ? 'X' : (string) $check);
    }

    private static function isbn13CheckDigit(string $firstTwelveDigits): string
    {
        $sum = 0;

        foreach (str_split($firstTwelveDigits) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - $sum % 10) % 10);
    }
}
