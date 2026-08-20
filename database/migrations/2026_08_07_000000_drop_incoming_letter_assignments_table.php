<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('incoming_letters')
            ->whereIn('status', ['diteruskan_ke_divisi', 'ditugaskan_ke_anggota'])
            ->update(['status' => 'selesai']);

        Schema::dropIfExists('incoming_letter_assignments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('incoming_letter_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_letter_id')
                ->unique()
                ->constrained('incoming_letters')
                ->cascadeOnDelete();
            $table->foreignId('assigned_by')
                ->index()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('assigned_to')
                ->index()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('division_id')
                ->index()
                ->constrained('divisions')
                ->restrictOnDelete();
            $table->text('instruction')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('assigned_at')->index();
            $table->timestamps();
        });
    }
};
