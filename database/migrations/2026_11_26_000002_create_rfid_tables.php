<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('device_model')->nullable();
            $table->string('pattern')->nullable();
            $table->unsignedSmallInteger('uid_length')->nullable();
            $table->string('suffix')->nullable();
            $table->string('version')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('rfid_cards', function (Blueprint $table) {
            $table->id();
            $table->string('uid_text')->unique();
            $table->string('card_type')->default('em125k');
            $table->string('status')->default('active'); // active|unassigned|lost|damaged
            $table->date('issued_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('student_card_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfid_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->string('status')->default('active');
            $table->string('active_flag')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['rfid_card_id', 'active_flag']);
            $table->unique(['student_id', 'active_flag']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('terminal_code');
            $table->string('type')->default('in'); // in|out
            $table->string('location')->nullable();
            $table->foreignId('reader_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'terminal_code']);
        });

        Schema::create('terminal_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('label')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('registration_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reader_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('running'); // running|paused|finished|cancelled
            $table->json('snapshot')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('registration_scan_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_session_id')->constrained()->cascadeOnDelete();
            $table->string('uid_text')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome');
            $table->json('detail')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index('registration_session_id');
        });

        // Catatan mentah setiap tap — tidak pernah dihapus.
        Schema::create('raw_rfid_events', function (Blueprint $table) {
            $table->id();
            $table->char('event_id', 36)->unique();
            $table->foreignId('terminal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('uid_text');
            $table->string('event_type'); // entry|exit
            $table->dateTime('event_time');
            $table->string('timezone_offset')->nullable();
            $table->dateTime('received_at');
            $table->unsignedBigInteger('sequence_number')->nullable();
            $table->string('time_quality')->default('ok');
            $table->string('outcome')->nullable(); // hasil evaluasi, untuk ack & audit
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['terminal_id', 'sequence_number']);
            $table->index('event_time');
            $table->index('uid_text');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_rfid_events');
        Schema::dropIfExists('registration_scan_attempts');
        Schema::dropIfExists('registration_sessions');
        Schema::dropIfExists('device_pairing_codes');
        Schema::dropIfExists('terminal_tokens');
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('student_card_assignments');
        Schema::dropIfExists('rfid_cards');
        Schema::dropIfExists('reader_profiles');
    }
};
