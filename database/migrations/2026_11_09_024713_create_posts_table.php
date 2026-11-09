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
        // News on the public site, written by super admins. Uzbek is required, Russian optional.
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title_uz');
            $table->string('title_ru')->nullable();
            // Markdown; rendered with raw HTML escaped.
            $table->text('body_uz');
            $table->text('body_ru')->nullable();
            $table->string('image_path')->nullable();
            // Null = draft; a future time = scheduled.
            $table->timestamp('published_at')->nullable();
            // What the post is about, shown as links and on those public pages.
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('edition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('published_at');
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
        Schema::dropIfExists('posts');
    }
};
