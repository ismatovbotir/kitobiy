<?php

/*
|--------------------------------------------------------------------------
| Catalog reference data import (Wikidata)
|--------------------------------------------------------------------------
|
| Countries whose publishers and famous writers are imported weekly
| (php artisan catalog:import-publishers / catalog:import-authors).
| Uzbekistan first, then the CIS.
|
| qid           - the country on Wikidata (publishers: P17 country, writers: P27 citizenship)
| language_qid  - writers who wrote in this language count too (catches Soviet-era
|                 and older writers whose citizenship is the USSR or an empire)
| min_sitelinks - how "famous" a writer must be: the number of Wikipedia
|                 language versions with an article about them
|
*/

return [

    'import_countries' => [
        'UZ' => ['qid' => 'Q265', 'language_qid' => 'Q9264', 'min_sitelinks' => 3],
        'KZ' => ['qid' => 'Q232', 'language_qid' => 'Q9252', 'min_sitelinks' => 8],
        'KG' => ['qid' => 'Q813', 'language_qid' => 'Q9255', 'min_sitelinks' => 8],
        'TJ' => ['qid' => 'Q863', 'language_qid' => 'Q9260', 'min_sitelinks' => 8],
        'TM' => ['qid' => 'Q874', 'language_qid' => 'Q9267', 'min_sitelinks' => 8],
        'RU' => ['qid' => 'Q159', 'language_qid' => 'Q7737', 'min_sitelinks' => 40],
        'BY' => ['qid' => 'Q184', 'language_qid' => 'Q9091', 'min_sitelinks' => 15],
        'AM' => ['qid' => 'Q399', 'language_qid' => 'Q8785', 'min_sitelinks' => 12],
        'AZ' => ['qid' => 'Q227', 'language_qid' => 'Q9292', 'min_sitelinks' => 12],
        'MD' => ['qid' => 'Q217', 'language_qid' => null, 'min_sitelinks' => 12],
    ],

    /*
     * Occupations that make someone a writer: writer, poet, novelist, playwright.
     */
    'writer_occupations' => ['Q36180', 'Q49757', 'Q6625963', 'Q214917'],

    /*
     * Where uploaded cover photos are stored. Moving to object storage later
     * only means pointing this at another disk.
     */
    'cover_disk' => env('COVER_DISK', 'public'),

];
