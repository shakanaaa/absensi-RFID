<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\RawRfidEvent;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\SchoolDayService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman absensi harian: status tiap siswa per kelas untuk satu tanggal,
 * plus feed tap RFID terbaru.
 */
class AttendanceController extends Controller
{
    public function index(Request $request, SchoolDayService $days): Response
    {
        $user = $request->user();
        $school = School::with('settings')->findOrFail($user->school_id);
        $tz = $school->timezone ?: config('app.timezone');

        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : now($tz)->toDateString();

        $isSchoolDay = $days->isEffectiveSchoolDay($school->id, $date);
        $today = now($tz)->toDateString();
        $pastCheckout = $school->settings?->check_out_time !== null
            && ($date < $today
                || ($date === $today && now($tz)->format('H:i:s') > $school->settings->check_out_time->format('H:i:s')));

        $classes = SchoolClass::where('school_id', $school->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $classId = $request->integer('class_id') ?: ($classes->first()?->id);

        $class = $classes->firstWhere('id', $classId);

        $students = $class
            ? \App\Models\Student::where('school_id', $school->id)
                ->where('status', 'active')
                ->whereHas('memberships', fn ($m) => $m->where('class_id', $class->id)->whereNull('to_date'))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $sessions = AttendanceSession::where('school_id', $school->id)
            ->whereDate('school_date', $date)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $rows = $students->map(function ($s) use ($sessions, $tz, $isSchoolDay, $pastCheckout) {
            $session = $sessions->get($s->id);
            $status = $session?->attendance_status ?? AttendanceSession::STATUS_BELUM_MASUK;

            if ($isSchoolDay && $pastCheckout && $status === AttendanceSession::STATUS_BELUM_MASUK) {
                $status = AttendanceSession::STATUS_TIDAK_HADIR;
            }

            return [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $status,
                'status_label' => AttendanceSession::statusLabel($status),
                'check_in_at' => $session?->check_in_at?->setTimezone($tz)->format('H:i'),
                'check_out_at' => $session?->check_out_at?->setTimezone($tz)->format('H:i'),
            ];
        });

        $summary = $rows->countBy('status');

        // event_time disimpan UTC — batas bawah dalam zona waktu sekolah.
        $feedSince = Carbon::parse($date.' 00:00:00', $tz)->subDay()->setTimezone('UTC');

        $recentTaps = RawRfidEvent::whereHas('terminal', fn ($q) => $q->where('school_id', $school->id))
            ->where('event_time', '>=', $feedSince)
            ->with(['student:id,name', 'terminal:id,terminal_code,type'])
            ->latest('event_time')
            ->limit(15)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'student' => $e->student?->name,
                'uid' => $e->uid_text,
                'direction' => $e->event_type === 'exit' ? 'out' : 'in',
                'terminal' => $e->terminal?->terminal_code,
                'time' => $e->event_time->setTimezone($tz)->format('H:i:s'),
                'outcome' => $e->outcome,
            ]);

        return Inertia::render('Attendance/Index', [
            'date' => $date,
            'isSchoolDay' => $isSchoolDay,
            'classes' => $classes,
            'classId' => $classId,
            'students' => $rows,
            'summary' => [
                'hadir' => $summary['hadir'] ?? 0,
                'terlambat' => $summary['terlambat'] ?? 0,
                'tidak_hadir' => $summary['tidak_hadir'] ?? 0,
                'belum_masuk' => $summary['belum_masuk'] ?? 0,
            ],
            'recentTaps' => $recentTaps,
        ]);
    }
}
