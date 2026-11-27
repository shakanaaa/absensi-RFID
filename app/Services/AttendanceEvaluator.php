<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\RawRfidEvent;
use App\Models\RfidCard;
use App\Models\SchoolSetting;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Evaluasi tap RFID menjadi absensi harian. Satu baris attendance_sessions
 * per siswa per tanggal; raw_rfid_events selalu menyimpan fakta tap.
 *
 *  terminal 'in'  : check_in_at kosong → isi; status hadir bila
 *                   event_time <= check_in_time, selain itu terlambat.
 *                   check_in_at sudah terisi → tidak berubah.
 *  terminal 'out' : check_out_at kosong & sudah ada check_in → isi jam
 *                   pulang aktual. Tanpa check_in → tidak mengubah sesi.
 *  Hari non-efektif → outcome 'libur', tanpa sesi.
 */
class AttendanceEvaluator
{
    public const RULE_VERSION = 'v2.0';

    public function evaluate(RawRfidEvent $event): string
    {
        $card = RfidCard::where('uid_text', $event->uid_text)->first();
        $student = $card ? $this->resolveStudent($card) : null;

        if (! $student) {
            $outcome = $card && ! in_array($card->status, CardService::ASSIGNABLE, true)
                ? 'inactive_card'
                : 'unknown_card';

            return $this->record($event, $outcome);
        }

        $event->update(['student_id' => $student->id]);

        $terminal = $event->terminal;
        $school = $terminal->school;
        $tz = $school->timezone ?: config('app.timezone');
        $eventTime = $event->event_time->setTimezone($tz);
        $schoolDate = $eventTime->toDateString();

        if (! app(SchoolDayService::class)->isEffectiveSchoolDay($school->id, $schoolDate)) {
            return $this->record($event, 'libur');
        }

        $settings = SchoolSetting::where('school_id', $school->id)->first();

        // Debounce: tap kedua untuk siswa+arah yang sama dalam jendela
        // debounce dianggap pembacaan ganda (aman untuk sync offline).
        $lastTap = RawRfidEvent::where('student_id', $student->id)
            ->where('terminal_id', $terminal->id)
            ->where('id', '!=', $event->id)
            ->latest('event_time')
            ->first();

        if ($lastTap
            && abs($event->event_time->diffInSeconds($lastTap->event_time)) <= config('attendance.debounce_seconds', 3)) {
            return $this->record($event, 'ignored_duplicate_scan');
        }

        return DB::transaction(function () use ($event, $student, $school, $schoolDate, $eventTime, $settings, $terminal) {
            $session = AttendanceSession::where('student_id', $student->id)
                ->whereDate('school_date', $schoolDate)
                ->lockForUpdate()
                ->first()
                ?? AttendanceSession::create([
                    'student_id' => $student->id,
                    'school_id' => $school->id,
                    'school_date' => $schoolDate,
                    'attendance_status' => AttendanceSession::STATUS_BELUM_MASUK,
                ]);

            $outcome = match ($terminal->type) {
                'in' => $this->handleCheckIn($session, $eventTime, $settings),
                'out' => $this->handleCheckOut($session, $eventTime),
                default => 'ignored',
            };

            return $this->record($event, $outcome);
        });
    }

    private function handleCheckIn(AttendanceSession $session, $eventTime, ?SchoolSetting $settings): string
    {
        if ($session->check_in_at !== null) {
            return 'sudah_di_sekolah';
        }

        // Disimpan sebagai UTC (app timezone); perbandingan memakai jam lokal.
        $session->check_in_at = $eventTime->copy()->setTimezone(config('app.timezone'));

        $checkInLimit = $settings?->check_in_time;
        $isLate = $checkInLimit !== null
            && $eventTime->format('H:i:s') > $checkInLimit->format('H:i:s');

        $session->attendance_status = $isLate
            ? AttendanceSession::STATUS_TERLAMBAT
            : AttendanceSession::STATUS_HADIR;
        $session->save();

        return $session->attendance_status;
    }

    private function handleCheckOut(AttendanceSession $session, $eventTime): string
    {
        // OUT tanpa IN sebelumnya tidak mengubah status kehadiran.
        if ($session->check_in_at === null) {
            return 'tanpa_check_in';
        }

        if ($session->check_out_at !== null) {
            return 'sudah_pulang';
        }

        $session->check_out_at = $eventTime->copy()->setTimezone(config('app.timezone'));
        $session->save();

        return 'pulang';
    }

    /** Kartu → siswa aktif lewat assignment aktif; kartu non-aktif ditolak. */
    private function resolveStudent(RfidCard $card): ?Student
    {
        if (! in_array($card->status, CardService::ASSIGNABLE, true)) {
            return null;
        }

        $assignment = $card->assignments()
            ->where('status', 'active')
            ->where('active_flag', CardService::ACTIVE_FLAG)
            ->with('student')
            ->first();

        $student = $assignment?->student;

        return $student && $student->status === 'active' ? $student : null;
    }

    private function record(RawRfidEvent $event, string $outcome): string
    {
        $event->update(['outcome' => $outcome]);

        return $outcome;
    }
}
