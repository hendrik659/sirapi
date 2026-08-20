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
        Schema::create('outgoing_letters', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('letter_number', 100)->unique();
            $table->date('letter_date')->index();
            $table->string('recipient_name');
            $table->text('recipient_address')->nullable();
            $table->string('subject');
            $table->foreignId('division_id')
                ->index()
                ->constrained('divisions')
                ->restrictOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('document_path');
            $table->string('original_document_name');
            $table->string('document_mime_type', 100);
            $table->unsignedBigInteger('document_size');
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_letters');
    }
};
