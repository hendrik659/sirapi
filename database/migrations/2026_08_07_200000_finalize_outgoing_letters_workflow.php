<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->normalizeReferenceCodes(3);

        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
        });

        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['archived_at', 'archived_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        $this->normalizeReferenceCodes(6);
    }

    private function normalizeReferenceCodes(int $minimumDigits): void
    {
        $letters = DB::table('outgoing_letters')
            ->orderBy('id')
            ->get(['id', 'letter_date']);

        DB::transaction(function () use ($letters, $minimumDigits) {
            foreach ($letters as $letter) {
                DB::table('outgoing_letters')
                    ->where('id', $letter->id)
                    ->update(['reference_code' => 'TEMP-SK4-'.Str::uuid()]);
            }

            foreach ($letters as $letter) {
                $year = substr((string) $letter->letter_date, 0, 4);

                DB::table('outgoing_letters')
                    ->where('id', $letter->id)
                    ->update([
                        'reference_code' => sprintf(
                            'SK-%s-%0'.$minimumDigits.'d',
                            $year,
                            $letter->id,
                        ),
                    ]);
            }
        });
    }
};
