<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per siswa per tanggal — sumber kebenaran absensi harian.
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('school_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            // belum_masuk|hadir|terlambat|tidak_hadir
            $table->string('attendance_status')->default('belum_masuk');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'school_date']);
            $table->index(['school_id', 'school_date']);
            $table->index('attendance_status');
        });

        // Pengaturan operasional per sekolah (jam, WA, dll).
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->time('check_in_time')->default('07:00:00');
            $table->time('check_out_time')->default('15:00:00');
            $table->string('whatsapp_number')->nullable();
            $table->time('report_send_time')->nullable();
            $table->timestamps();
        });

        // Hari libur non-efektif (libur nasional hasil generate + manual).
        Schema::create('school_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name');
            $table->timestamps();

            $table->unique(['school_id', 'holiday_date']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type')->default('user');
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->json('before_ref')->nullable();
            $table->json('after_ref')->nullable();
            $table->text('reason')->nullable();
            $table->string('request_id')->nullable();
            $table->string('source')->default('web');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('school_holidays');
        Schema::dropIfExists('school_settings');
        Schema::dropIfExists('attendance_sessions');
    }
};
