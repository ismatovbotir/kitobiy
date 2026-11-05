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
        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('search_name')->default('');
            $table->char('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('website', 2048)->nullable();
            $table->string('wikidata_id', 20)->nullable()->unique();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index('name');
            $table->index('search_name');
            $table->index('is_verified');
        });

        Schema::create('publisher_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('search_name')->default('');
            $table->string('language', 10)->nullable();

            $table->unique(['publisher_id', 'name']);
            $table->index('name');
            $table->index('search_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publisher_aliases');
        Schema::dropIfExists('publishers');
    }
};
