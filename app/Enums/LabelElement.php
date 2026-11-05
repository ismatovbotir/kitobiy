<?php

namespace App\Enums;

/**
 * A piece of content a super admin can place on a label template.
 * Values that come from a copy are filled in when labels are printed (Phase 2).
 */
enum LabelElement: string
{
    case Title = 'title';
    case Authors = 'authors';
    case Library = 'library';
    case InventoryCode = 'inventory_code';
    case Isbn = 'isbn';
    case Genres = 'genres';
    case QrCode = 'qr_code';
    case Wordmark = 'wordmark';
    case Text = 'text';

    /**
     * Name shown in the designer's element menu.
     */
    public function label(): string
    {
        return match ($this) {
            self::Title => __('Title'),
            self::Authors => __('Authors'),
            self::Library => __('Library name'),
            self::InventoryCode => __('Book ID'),
            self::Isbn => __('ISBN'),
            self::Genres => __('Genres'),
            self::QrCode => __('QR code'),
            self::Wordmark => __('Logo'),
            self::Text => __('Custom text'),
        };
    }

    /**
     * Example content for the designer preview.
     */
    public function sample(): string
    {
        return match ($this) {
            self::Title => 'O‘tkan kunlar',
            self::Authors => 'Abdulla Qodiriy',
            self::Library => __('Family library'),
            self::InventoryCode => 'KTB-0042',
            self::Isbn => '978-9943-01-234-5',
            self::Genres => 'Tarixiy roman',
            self::QrCode, self::Wordmark => config('app.name'),
            self::Text => 'Ex libris',
        };
    }

    /**
     * Whether the element keeps a square shape (the QR code).
     */
    public function isSquare(): bool
    {
        return $this === self::QrCode;
    }

    /**
     * Size in mm and font size in pt for a newly added element.
     *
     * @return array{w: float, h: float, font_size: float}
     */
    public function defaults(): array
    {
        return match ($this) {
            self::QrCode => ['w' => 15.0, 'h' => 15.0, 'font_size' => 8.0],
            self::Title => ['w' => 30.0, 'h' => 8.0, 'font_size' => 10.0],
            self::Wordmark => ['w' => 22.0, 'h' => 6.0, 'font_size' => 9.0],
            default => ['w' => 30.0, 'h' => 5.0, 'font_size' => 7.0],
        };
    }
}
