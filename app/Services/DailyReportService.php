<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rekap absensi harian per kelas + formatter teks untuk link wa.me.
 * Status "tidak_hadir" dihitung untuk siswa aktif yang belum tap IN
 * setelah jam pulang lewat; sebelum itu statusnya "belum_masuk".
 */
class DailyReportService
{
    /**
     * Rekap satu tanggal: baris per kelas + detail siswa.
     *
     * @return array{date:string, is_school_day:bool, classes:array}
     */
    public function recap(School $school, string $date): array
    {
        $settings = $school->settings;
        $isSchoolDay = app(SchoolDayService::class)->isEffectiveSchoolDay($school->id, $date);

        // Lewat jam pulang → siswa tanpa check_in dianggap tidak_hadir.
        $pastCheckout = $settings?->check_out_time !== null
            && Carbon::now($school->timezone)->format('H:i:s') > $settings->check_out_time->format('H:i:s')
            && $date <= Carbon::now($school->timezone)->toDateString();

        $classes = SchoolClass::where('school_id', $school->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($classes as $class) {
            $students = Student::where('school_id', $school->id)
                ->where('status', 'active')
                ->whereHas('memberships', fn ($m) => $m
                    ->where('class_id', $class->id)->whereNull('to_date'))
                ->orderBy('name')
                ->get(['id', 'student_no', 'name']);

            $sessions = AttendanceSession::where('school_id', $school->id)
                ->whereDate('school_date', $date)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->keyBy('student_id');

            $rows = [];
            $counts = ['hadir' => 0, 'terlambat' => 0, 'tidak_hadir' => 0, 'belum_masuk' => 0];

            foreach ($students as $student) {
                $session = $sessions->get($student->id);
                $status = $session?->attendance_status ?? AttendanceSession::STATUS_BELUM_MASUK;

                if ($isSchoolDay && $pastCheckout && $status === AttendanceSession::STATUS_BELUM_MASUK) {
                    $status = AttendanceSession::STATUS_TIDAK_HADIR;
                }

                $counts[match ($status) {
                    'hadir' => 'hadir',
                    'terlambat' => 'terlambat',
                    'tidak_hadir' => 'tidak_hadir',
                    default => 'belum_masuk',
                }]++;

                $rows[] = [
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'status' => $status,
                    'check_in_at' => $session?->check_in_at?->setTimezone($school->timezone)->format('H:i'),
                    'check_out_at' => $session?->check_out_at?->setTimezone($school->timezone)->format('H:i'),
                ];
            }

            $result[] = [
                'class_id' => $class->id,
                'name' => $class->name,
                'total' => $students->count(),
                'counts' => $counts,
                'students' => $rows,
            ];
        }

        return ['date' => $date, 'is_school_day' => $isSchoolDay, 'classes' => $result];
    }

    /** Teks laporan harian siap tempel ke wa.me. */
    public function whatsappText(School $school, string $date, array $recap): string
    {
        $label = Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y');
        $lines = ["*Rekap Absensi {$school->name}*", $label, ''];

        if (! $recap['is_school_day']) {
            $lines[] = 'Hari ini bukan hari sekolah efektif.';

            return implode("\n", $lines);
        }

        foreach ($recap['classes'] as $class) {
            $c = $class['counts'];
            $lines[] = "*{$class['name']}* ({$class['total']} siswa)";
            $lines[] = "Hadir: {$c['hadir']} | Terlambat: {$c['terlambat']} | Tidak Hadir: {$c['tidak_hadir']} | Belum Masuk: {$c['belum_masuk']}";

            $late = collect($class['students'])->where('status', 'terlambat')->pluck('name');
            $absent = collect($class['students'])->where('status', 'tidak_hadir')->pluck('name');

            if ($late->isNotEmpty()) {
                $lines[] = 'Terlambat: '.$late->implode(', ');
            }
            if ($absent->isNotEmpty()) {
                $lines[] = 'Tidak Hadir: '.$absent->implode(', ');
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** URL wa.me dengan teks laporan ter-encode. Null bila nomor WA kosong. */
    public function whatsappUrl(School $school, string $date, array $recap): ?string
    {
        $number = preg_replace('/\D+/', '', (string) ($school->settings?->whatsapp_number ?? ''));

        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($this->whatsappText($school, $date, $recap));
    }
}
