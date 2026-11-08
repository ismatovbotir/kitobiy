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

        // People who keep a publisher's book list. A claim waits for a super admin (approved_at null).
        Schema::create('publisher_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Their job at the publisher and a phone number, so the super admin can check the claim.
            $table->string('position', 100);
            $table->string('phone', 30);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['publisher_id', 'user_id']);
            $table->index('user_id');
            $table->index('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publisher_user');
        Schema::dropIfExists('publisher_aliases');
        Schema::dropIfExists('publishers');
    }
};
