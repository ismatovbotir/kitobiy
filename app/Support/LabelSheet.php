<?php

namespace App\Support;

/**
 * How labels of one size fit on an A4 sheet. With the margins and gap below, the
 * result matches the usual sheets: 38.1×21.2 gives 65 labels (5×13), 63.5×38.1
 * gives 21 (3×7) and 99.1×67.7 gives 8 (2×4), centred on the page like the real sheets.
 */
class LabelSheet
{
    public const float PAGE_WIDTH_MM = 210;

    public const float PAGE_HEIGHT_MM = 297;

    private const float MIN_MARGIN_X_MM = 4;

    private const float MIN_MARGIN_Y_MM = 8;

    private const float COLUMN_GAP_MM = 2.5;

    public function __construct(public float $labelWidthMm, public float $labelHeightMm) {}

    public function columns(): int
    {
        $usable = self::PAGE_WIDTH_MM - 2 * self::MIN_MARGIN_X_MM;

        return max(1, (int) floor(($usable + self::COLUMN_GAP_MM) / ($this->labelWidthMm + self::COLUMN_GAP_MM)));
    }

    public function rows(): int
    {
        $usable = self::PAGE_HEIGHT_MM - 2 * self::MIN_MARGIN_Y_MM;

        return max(1, (int) floor($usable / $this->labelHeightMm));
    }

    public function labelsPerPage(): int
    {
        return $this->columns() * $this->rows();
    }

    /**
     * The top-left corner of a label, counted across then down from 0.
     *
     * @return array{x: float, y: float}
     */
    public function position(int $slot): array
    {
        $columns = $this->columns();
        $usedWidth = $columns * $this->labelWidthMm + ($columns - 1) * self::COLUMN_GAP_MM;
        $marginX = max(0, (self::PAGE_WIDTH_MM - $usedWidth) / 2);
        $marginY = max(0, (self::PAGE_HEIGHT_MM - $this->rows() * $this->labelHeightMm) / 2);

        return [
            'x' => round($marginX + ($slot % $columns) * ($this->labelWidthMm + self::COLUMN_GAP_MM), 2),
            'y' => round($marginY + (int) floor($slot / $columns) * $this->labelHeightMm, 2),
        ];
    }

    /**
     * Lay items out over pages. Skipping leaves the first labels of the first page
     * blank, so a partly used sheet can go through the printer again.
     *
     * @template T
     *
     * @param  iterable<T>  $items
     * @return list<list<array{x: float, y: float, item: T}>>
     */
    public function pages(iterable $items, int $skip = 0): array
    {
        $perPage = $this->labelsPerPage();
        $slot = max(0, min($skip, $perPage - 1));
        $pages = [];

        foreach ($items as $item) {
            $pages[(int) floor($slot / $perPage)][] = [...$this->position($slot % $perPage), 'item' => $item];
            $slot++;
        }

        return array_values($pages);
    }
}
