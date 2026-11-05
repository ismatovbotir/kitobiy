<?php

namespace App\Models;

use App\Enums\LabelElement;
use Database\Factories\LabelTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * A printable book label layout, designed by a super admin.
 * Element positions and sizes are in mm from the top-left corner; font sizes in pt.
 * The starting templates are inserted by the migration.
 *
 * @property list<array{type: string, x: float, y: float, w: float, h: float, font_size: float, bold: bool, align: string, text: string|null}> $elements
 */
#[Fillable(['name', 'width_mm', 'height_mm', 'elements', 'position', 'is_active'])]
class LabelTemplate extends Model
{
    /** @use HasFactory<LabelTemplateFactory> */
    use HasFactory;

    public const float MIN_SIZE_MM = 10;

    public const float MAX_SIZE_MM = 210;

    public const int MAX_ELEMENTS = 20;

    public const array ALIGNMENTS = ['left', 'center', 'right'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width_mm' => 'float',
            'height_mm' => 'float',
            'elements' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<LabelTemplate>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<LabelTemplate>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * Validation rules for a template, keyed for a form with
     * name / widthMm / heightMm / position / isActive / elements.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'widthMm' => ['required', 'numeric', 'between:'.self::MIN_SIZE_MM.','.self::MAX_SIZE_MM],
            'heightMm' => ['required', 'numeric', 'between:'.self::MIN_SIZE_MM.','.self::MAX_SIZE_MM],
            'position' => ['required', 'integer', 'between:0,9999'],
            'isActive' => ['boolean'],
            'elements' => ['array', 'max:'.self::MAX_ELEMENTS],
            'elements.*.type' => ['required', Rule::enum(LabelElement::class)],
            'elements.*.x' => ['required', 'numeric', 'min:0'],
            'elements.*.y' => ['required', 'numeric', 'min:0'],
            'elements.*.w' => ['required', 'numeric', 'min:2'],
            'elements.*.h' => ['required', 'numeric', 'min:2'],
            'elements.*.font_size' => ['required', 'numeric', 'between:4,48'],
            'elements.*.bold' => ['boolean'],
            'elements.*.align' => ['required', Rule::in(self::ALIGNMENTS)],
            'elements.*.text' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Keep elements inside the label (positions rounded to 0.1 mm).
     *
     * @param  array<int, array<string, mixed>>  $elements
     * @return list<array{type: string, x: float, y: float, w: float, h: float, font_size: float, bold: bool, align: string, text: string|null}>
     */
    public static function fitElements(array $elements, float $widthMm, float $heightMm): array
    {
        return array_values(array_map(function (array $element) use ($widthMm, $heightMm): array {
            $w = round(min((float) $element['w'], $widthMm), 1);
            $h = round(min((float) $element['h'], $heightMm), 1);

            return [
                'type' => (string) $element['type'],
                'x' => round(min((float) $element['x'], $widthMm - $w), 1),
                'y' => round(min((float) $element['y'], $heightMm - $h), 1),
                'w' => $w,
                'h' => $h,
                'font_size' => round((float) $element['font_size'], 1),
                'bold' => (bool) ($element['bold'] ?? false),
                'align' => (string) $element['align'],
                'text' => $element['type'] === LabelElement::Text->value ? trim((string) ($element['text'] ?? '')) : null,
            ];
        }, $elements));
    }

    /**
     * e.g. "63.5 × 38.1 mm".
     */
    public function sizeLabel(): string
    {
        return rtrim(rtrim(number_format($this->width_mm, 1, '.', ''), '0'), '.')
            .' × '
            .rtrim(rtrim(number_format($this->height_mm, 1, '.', ''), '0'), '.')
            .' mm';
    }
}
