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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->after('id')
                ->constrained('roles')
                ->restrictOnDelete();
            $table->foreignId('division_id')
                ->nullable()
                ->after('role_id')
                ->constrained('divisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
