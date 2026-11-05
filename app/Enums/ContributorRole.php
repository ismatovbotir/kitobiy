<?php

namespace App\Enums;

/**
 * How a person contributed to an edition, after the MARC relator / ONIX
 * contributor roles most often printed in home-library books.
 */
enum ContributorRole: string
{
    case Author = 'author';
    case Translator = 'translator';
    case Compiler = 'compiler';
    case Editor = 'editor';
    case Illustrator = 'illustrator';

    /**
     * Singular name, e.g. "Translator".
     */
    public function label(): string
    {
        return match ($this) {
            self::Author => __('Author'),
            self::Translator => __('Translator'),
            self::Compiler => __('Compiler'),
            self::Editor => __('Editor'),
            self::Illustrator => __('Illustrator'),
        };
    }

    /**
     * Form label for a comma-separated list of names, e.g. "Translators".
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Author => __('Authors'),
            self::Translator => __('Translators'),
            self::Compiler => __('Compilers'),
            self::Editor => __('Editors'),
            self::Illustrator => __('Illustrators'),
        };
    }

    /**
     * Roles shown behind "More people" in the forms.
     *
     * @return list<self>
     */
    public static function secondary(): array
    {
        return [self::Compiler, self::Editor, self::Illustrator];
    }
}
