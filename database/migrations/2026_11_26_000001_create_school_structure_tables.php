<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->timestamps();
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft'); // draft|active|closed
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('level')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('student_no');
            $table->string('name');
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('status')->default('active'); // active|lulus|nonaktif
            $table->timestamps();

            $table->unique(['school_id', 'student_no']);
        });

        Schema::create('student_class_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->unsignedSmallInteger('attendance_number')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'from_date']);
            $table->index(['class_id', 'from_date']);
        });

        // Perluasan tabel users bawaan Laravel untuk multi-role sekolah.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('petugas_piket')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->string('status')->default('active')->after('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_memberships');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('schools');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
            $table->dropColumn(['role', 'phone', 'status']);
        });
    }
};
