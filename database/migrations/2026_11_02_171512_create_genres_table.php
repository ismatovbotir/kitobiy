<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The starting genres: a small flat list modelled on the top levels of
     * Thema/BISAC, sized for home libraries. A super admin edits them later.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}> slug, uz, ru, en
     */
    private const array GENRES = [
        ['prose', 'Badiiy nasr', 'Проза', 'Prose & novels'],
        ['poetry', 'She’riyat', 'Поэзия', 'Poetry'],
        ['drama', 'Dramaturgiya', 'Драматургия', 'Drama & plays'],
        ['detective', 'Detektiv va triller', 'Детектив и триллер', 'Detective & thriller'],
        ['fantasy', 'Fantastika va fentezi', 'Фантастика и фэнтези', 'Fantasy & science fiction'],
        ['romance', 'Ishqiy roman', 'Любовный роман', 'Romance'],
        ['historical_fiction', 'Tarixiy roman', 'Исторический роман', 'Historical fiction'],
        ['children', 'Bolalar adabiyoti', 'Детская литература', 'Children’s books'],
        ['teen', 'O‘smirlar uchun', 'Для подростков', 'Young adult'],
        ['history', 'Tarix', 'История', 'History'],
        ['biography', 'Biografiya va xotiralar', 'Биографии и мемуары', 'Biography & memoirs'],
        ['religion', 'Din', 'Религия', 'Religion'],
        ['philosophy', 'Falsafa va psixologiya', 'Философия и психология', 'Philosophy & psychology'],
        ['self_help', 'Shaxsiy rivojlanish va biznes', 'Саморазвитие и бизнес', 'Self-help & business'],
        ['science', 'Fan va tabiat', 'Наука и природа', 'Science & nature'],
        ['health', 'Salomatlik va tibbiyot', 'Здоровье и медицина', 'Health & medicine'],
        ['society', 'Huquq, siyosat va jamiyat', 'Право, политика и общество', 'Law, politics & society'],
        ['textbooks', 'Darsliklar va til o‘rganish', 'Учебники и изучение языков', 'Textbooks & language learning'],
        ['reference', 'Lug‘atlar va ma’lumotnomalar', 'Словари и справочники', 'Dictionaries & reference'],
        ['arts', 'San’at, musiqa va hobbi', 'Искусство, музыка и хобби', 'Art, music & hobbies'],
        ['home', 'Pazandachilik va uy-ro‘zg‘or', 'Кулинария и дом', 'Cooking & home'],
        ['technology', 'Kompyuter va texnologiyalar', 'Компьютеры и технологии', 'Computers & technology'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_uz');
            $table->string('name_ru');
            $table->string('name_en');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        $now = now();

        DB::table('genres')->insert(array_map(fn (array $genre, int $position): array => [
            'slug' => $genre[0],
            'name_uz' => $genre[1],
            'name_ru' => $genre[2],
            'name_en' => $genre[3],
            'position' => $position + 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::GENRES, array_keys(self::GENRES)));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
