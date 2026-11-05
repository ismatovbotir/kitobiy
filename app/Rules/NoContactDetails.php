<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Keeps phone numbers, emails, usernames and links off public pages, so people
 * don't publish their contact details by habit. It catches the usual forms, not
 * someone determined to spell a number out in words.
 */
class NoContactDetails implements ValidationRule
{
    /** @var list<string> */
    private const array PATTERNS = [
        '/(?:\d[\s\-().]*){7,}/u',
        '/[^\s@]+@[^\s@]+\.[^\s@]+/u',
        '/(?:^|\s)@\w{3,}/u',
        '/(?:https?:\/\/|www\.|\bt\.me\b|\btelegram\.me\b)/iu',
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, (string) $value) === 1) {
                $fail(__('Please leave out phone numbers, emails, usernames and links.'));

                return;
            }
        }
    }
}
