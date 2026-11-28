<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            // Batas selesai terlambat: siswa tanpa check-in setelah jam ini
            // (dan sebelum jam pulang) dianggap terlambat. Null = nonaktif.
            $table->time('late_deadline_time')->nullable()->after('check_out_time');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn('late_deadline_time');
        });
    }
};
