<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            // ISO dayOfWeek: 1=Senin .. 7=Minggu. Null = default [6,7] (Sabtu-Minggu).
            $table->json('weekly_off_days')->nullable()->after('report_send_time');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn('weekly_off_days');
        });
    }
};
