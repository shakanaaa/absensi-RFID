<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            // Bagian laporan WA yang dikirim: counts|hadir|terlambat|
            // tidak_hadir|belum_masuk => bool. Null = bawaan.
            $table->json('wa_report_options')->nullable()->after('weekly_off_days');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn('wa_report_options');
        });
    }
};
