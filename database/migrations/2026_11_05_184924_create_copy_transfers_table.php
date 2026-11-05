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
        Schema::create('copy_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_library_name')->nullable();
            $table->timestamps();

            $table->index('copy_id');
            $table->index('offered_by');
            $table->index('accepted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copy_transfers');
    }
};
