<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan data tambahan ke tabel users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)
                ->nullable()
                ->after('email');

            $table->string('employee_number', 50)
                ->nullable()
                ->unique()
                ->after('phone');

            $table->string('position', 100)
                ->nullable()
                ->after('employee_number');

            $table->boolean('is_active')
                ->default(true)
                ->after('password');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('is_active');

            $table->softDeletes();
        });
    }

    /**
     * Menghapus kolom tambahan dari tabel users.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();

            $table->dropColumn([
                'phone',
                'employee_number',
                'position',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};