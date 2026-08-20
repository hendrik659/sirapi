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
        Schema::create('outgoing_letter_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outgoing_letter_id')
                ->index()
                ->constrained('outgoing_letters')
                ->cascadeOnDelete();
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
        Schema::dropIfExists('outgoing_letter_histories');
    }
};
