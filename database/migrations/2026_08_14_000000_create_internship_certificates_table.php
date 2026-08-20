<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('archive_code')->nullable()->unique();
            $table->string('participant_name')->index();
            $table->string('institution_name')->index();
            $table->string('major_name')->index();
            $table->date('start_date');
            $table->date('end_date')->index();
            $table->string('document_path');
            $table->string('original_document_name');
            $table->string('document_mime_type', 100);
            $table->unsignedBigInteger('document_size');
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_certificates');
    }
};
