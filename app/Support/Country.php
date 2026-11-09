<?php

namespace App\Support;

/**
 * Countries (ISO 3166-1), with Uzbekistan's regions (ISO 3166-2) and its main cities.
 * Names live in lang/{en,uz,ru}/countries.php and regions.php. Other countries have
 * no list of regions, so people type theirs. City lists are suggestions, not limits.
 */
class Country
{
    public const string UZBEKISTAN = 'UZ';

    /** @var list<string> */
    public const array CODES = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ', 'BA', 'BB', 'BD', 'BE',
        'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD',
        'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM',
        'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF',
        'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU',
        'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN',
        'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME',
        'MF', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA',
        'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM',
        'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI',
        'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK',
        'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI',
        'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
    ];

    /**
     * Shown first in the picker, in this order.
     *
     * @var list<string>
     */
    public const array POPULAR = ['UZ', 'KZ', 'KG', 'TJ', 'TM', 'RU', 'TR'];

    /**
     * Uzbekistan's regions and their main cities, in Uzbek Latin spelling.
     *
     * @var array<string, list<string>>
     */
    public const array UZBEK_REGIONS = [
        'UZ-AN' => ['Andijon', 'Asaka', 'Xonobod', 'Shahrixon', 'Qorasuv', 'Paxtaobod'],
        'UZ-BU' => ['Buxoro', 'Kogon', 'G‘ijduvon', 'Romitan', 'Qorako‘l', 'Vobkent'],
        'UZ-FA' => ['Farg‘ona', 'Qo‘qon', 'Marg‘ilon', 'Quvasoy', 'Rishton', 'Beshariq', 'Quva'],
        'UZ-JI' => ['Jizzax', 'G‘allaorol', 'Dashtobod', 'Do‘stlik'],
        'UZ-NG' => ['Namangan', 'Chust', 'Chortoq', 'Uchqo‘rg‘on', 'Pop', 'To‘raqo‘rg‘on'],
        'UZ-NW' => ['Navoiy', 'Zarafshon', 'Uchquduq', 'Nurota', 'Qiziltepa'],
        'UZ-QA' => ['Qarshi', 'Shahrisabz', 'Kitob', 'G‘uzor', 'Koson', 'Muborak'],
        'UZ-QR' => ['Nukus', 'Xo‘jayli', 'Qo‘ng‘irot', 'Beruniy', 'To‘rtko‘l', 'Mo‘ynoq', 'Chimboy'],
        'UZ-SA' => ['Samarqand', 'Kattaqo‘rg‘on', 'Urgut', 'Bulung‘ur', 'Ishtixon'],
        'UZ-SI' => ['Guliston', 'Yangiyer', 'Shirin', 'Sirdaryo', 'Boyovut'],
        'UZ-SU' => ['Termiz', 'Denov', 'Sho‘rchi', 'Sherobod', 'Boysun'],
        'UZ-TK' => ['Toshkent'],
        'UZ-TO' => ['Nurafshon', 'Angren', 'Chirchiq', 'Olmaliq', 'Bekobod', 'Ohangaron', 'Yangiyo‘l', 'Parkent', 'G‘azalkent'],
        'UZ-XO' => ['Urganch', 'Xiva', 'Hazorasp', 'Shovot', 'Gurlan'],
    ];

    /**
     * The country's name in the current locale.
     */
    public static function name(string $code): string
    {
        return __("countries.{$code}");
    }

    /**
     * Picker options in the current locale: the popular countries first, then all others A–Z.
     *
     * @return array{popular: array<string, string>, others: array<string, string>}
     */
    public static function options(): array
    {
        $popular = [];

        foreach (self::POPULAR as $code) {
            $popular[$code] = __("countries.{$code}");
        }

        $others = [];

        foreach (array_diff(self::CODES, self::POPULAR) as $code) {
            $others[$code] = __("countries.{$code}");
        }

        return ['popular' => $popular, 'others' => self::sortedByName($others)];
    }

    /**
     * Regions to choose from, or nothing for a country whose regions are typed by hand.
     *
     * @return array<string, string> code => name in the current locale
     */
    public static function regionOptions(?string $countryCode): array
    {
        if ($countryCode !== self::UZBEKISTAN) {
            return [];
        }

        $regions = [];

        foreach (array_keys(self::UZBEK_REGIONS) as $code) {
            $regions[$code] = __("regions.{$code}");
        }

        return self::sortedByName($regions);
    }

    /**
     * @return list<string>
     */
    public static function citySuggestions(?string $regionCode): array
    {
        return self::UZBEK_REGIONS[$regionCode] ?? [];
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, string>
     */
    private static function sortedByName(array $names): array
    {
        if (class_exists(\Collator::class)) {
            (new \Collator(app()->getLocale()))->asort($names);
        } else {
            uasort($names, fn (string $a, string $b): int => strcmp(mb_strtolower($a), mb_strtolower($b)));
        }

        return $names;
    }
}
