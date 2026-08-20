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
        Schema::create('incoming_letters', function (Blueprint $table) {
            $table->id();
            $table->string('agenda_number', 100)->unique();
            $table->string('letter_number', 100)->nullable();
            $table->string('sender_name');
            $table->string('addressed_to');
            $table->date('letter_date')->nullable();
            $table->date('received_date');
            $table->string('received_via', 100);
            $table->string('subject', 500);
            $table->text('summary')->nullable();
            $table->string('priority', 50);
            $table->foreignId('destination_division_id')
                ->nullable()
                ->constrained('divisions')
                ->nullOnDelete();
            $table->string('document_path');
            $table->string('original_document_name');
            $table->string('document_mime_type', 100);
            $table->unsignedBigInteger('document_size');
            $table->string('status', 50)->default('baru_diterima');
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_letters');
    }
};
