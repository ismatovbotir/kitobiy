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
        // Book fairs, author meetings, presentations on the public site, added by super admins.
        Schema::create('book_events', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title_uz');
            $table->string('title_ru')->nullable();
            // Markdown; rendered with raw HTML escaped.
            $table->text('body_uz');
            $table->text('body_ru')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            // A venue and city, an online link, or both.
            $table->string('place')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('online_url', 2048)->nullable();
            // Where to register or read more, e.g. the organiser's page.
            $table->string('info_url', 2048)->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('edition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'starts_at']);
            $table->index('publisher_id');
            $table->index('author_id');
            $table->index('edition_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_events');
    }
};
