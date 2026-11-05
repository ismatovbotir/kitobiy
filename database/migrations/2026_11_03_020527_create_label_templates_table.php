<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The starting templates, sized after common A4 label sheets
     * (Avery L7651 = 65 per sheet, L7160 = 21, L7165 = 8). A super admin edits them later.
     *
     * @var list<array{name: string, width_mm: float, height_mm: float, elements: list<array<string, mixed>>}>
     */
    private const array TEMPLATES = [
        [
            'name' => 'Kichik — 38×21 mm',
            'width_mm' => 38.1,
            'height_mm' => 21.2,
            'elements' => [
                ['type' => 'qr_code', 'x' => 2, 'y' => 2.5, 'w' => 16, 'h' => 16, 'font_size' => 8, 'bold' => false, 'align' => 'left', 'text' => null],
                ['type' => 'title', 'x' => 19.5, 'y' => 2.5, 'w' => 16.5, 'h' => 9, 'font_size' => 7, 'bold' => true, 'align' => 'left', 'text' => null],
                ['type' => 'inventory_code', 'x' => 19.5, 'y' => 13, 'w' => 16.5, 'h' => 5.5, 'font_size' => 7, 'bold' => false, 'align' => 'left', 'text' => null],
            ],
        ],
        [
            'name' => 'O‘rta — 63.5×38.1 mm',
            'width_mm' => 63.5,
            'height_mm' => 38.1,
            'elements' => [
                ['type' => 'library', 'x' => 3, 'y' => 3, 'w' => 38, 'h' => 5, 'font_size' => 6.5, 'bold' => false, 'align' => 'left', 'text' => null],
                ['type' => 'title', 'x' => 3, 'y' => 9, 'w' => 38, 'h' => 13, 'font_size' => 10, 'bold' => true, 'align' => 'left', 'text' => null],
                ['type' => 'authors', 'x' => 3, 'y' => 23, 'w' => 38, 'h' => 5.5, 'font_size' => 7.5, 'bold' => false, 'align' => 'left', 'text' => null],
                ['type' => 'inventory_code', 'x' => 3, 'y' => 30.5, 'w' => 38, 'h' => 5, 'font_size' => 7, 'bold' => true, 'align' => 'left', 'text' => null],
                ['type' => 'qr_code', 'x' => 43.5, 'y' => 9, 'w' => 17, 'h' => 17, 'font_size' => 8, 'bold' => false, 'align' => 'left', 'text' => null],
                ['type' => 'isbn', 'x' => 41, 'y' => 30.5, 'w' => 20, 'h' => 5, 'font_size' => 5, 'bold' => false, 'align' => 'right', 'text' => null],
            ],
        ],
        [
            'name' => 'Katta (ex libris) — 99×68 mm',
            'width_mm' => 99.1,
            'height_mm' => 67.7,
            'elements' => [
                ['type' => 'text', 'x' => 5, 'y' => 4, 'w' => 89, 'h' => 6, 'font_size' => 9, 'bold' => false, 'align' => 'center', 'text' => 'EX LIBRIS'],
                ['type' => 'library', 'x' => 5, 'y' => 11, 'w' => 89, 'h' => 8, 'font_size' => 14, 'bold' => true, 'align' => 'center', 'text' => null],
                ['type' => 'title', 'x' => 5, 'y' => 24, 'w' => 60, 'h' => 16, 'font_size' => 13, 'bold' => true, 'align' => 'left', 'text' => null],
                ['type' => 'authors', 'x' => 5, 'y' => 41, 'w' => 60, 'h' => 7, 'font_size' => 10, 'bold' => false, 'align' => 'left', 'text' => null],
                ['type' => 'genres', 'x' => 5, 'y' => 49, 'w' => 60, 'h' => 6, 'font_size' => 8, 'bold' => false, 'align' => 'left', 'text' => null],
                ['type' => 'inventory_code', 'x' => 5, 'y' => 57, 'w' => 30, 'h' => 6, 'font_size' => 9, 'bold' => true, 'align' => 'left', 'text' => null],
                ['type' => 'isbn', 'x' => 36, 'y' => 57.5, 'w' => 29, 'h' => 5, 'font_size' => 7, 'bold' => false, 'align' => 'left', 'text' => null],
                ['type' => 'qr_code', 'x' => 70, 'y' => 26, 'w' => 24, 'h' => 24, 'font_size' => 8, 'bold' => false, 'align' => 'left', 'text' => null],
                ['type' => 'wordmark', 'x' => 70, 'y' => 54, 'w' => 24, 'h' => 7, 'font_size' => 9, 'bold' => false, 'align' => 'center', 'text' => null],
            ],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('width_mm', 5, 1);
            $table->decimal('height_mm', 5, 1);
            $table->json('elements');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        $now = now();

        foreach (self::TEMPLATES as $index => $template) {
            DB::table('label_templates')->insert([
                ...$template,
                'elements' => json_encode($template['elements'], JSON_UNESCAPED_UNICODE),
                'position' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
