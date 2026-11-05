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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('borrower_library_id')->nullable()->constrained('libraries')->nullOnDelete();
            $table->string('borrower_name')->nullable();
            $table->string('borrower_phone', 40)->nullable();
            $table->date('due_on')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['copy_id', 'returned_at']);
            $table->index('lent_by');
            $table->index(['borrower_library_id', 'returned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
