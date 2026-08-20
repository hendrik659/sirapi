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
        Schema::create('incoming_letter_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_letter_id')
                ->unique()
                ->constrained('incoming_letters')
                ->cascadeOnDelete();
            $table->foreignId('reviewed_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('destination_division_id')
                ->constrained('divisions')
                ->restrictOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_letter_reviews');
    }
};
