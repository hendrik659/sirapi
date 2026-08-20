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
        Schema::create('incoming_letter_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_letter_id')
                ->index()
                ->constrained('incoming_letters')
                ->cascadeOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status')->index();
            $table->string('activity');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_letter_status_histories');
    }
};
