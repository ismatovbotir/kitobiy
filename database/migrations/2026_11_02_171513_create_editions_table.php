<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('search_text', 520)->default('');
            $table->string('language', 3)->nullable();
            $table->string('isbn_13', 13)->nullable()->unique();
            $table->string('isbn_10', 10)->nullable()->unique();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('cover_url', 2048)->nullable();
            $table->string('cover_path')->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('work_id');
            $table->index('publisher_id');
            $table->index('title');
            $table->index('created_by');
            $table->unique(['source', 'source_id']);
        });

        Schema::create('edition_genre', function (Blueprint $table) {
            $table->foreignId('edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->restrictOnDelete();

            $table->primary(['edition_id', 'genre_id']);
            $table->index('genre_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edition_genre');
        Schema::dropIfExists('editions');
    }
};
