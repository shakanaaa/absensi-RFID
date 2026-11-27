<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\RawRfidEvent;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $school = $user->school;
        $tz = $school?->timezone ?: config('app.timezone');
        $today = now($tz)->toDateString();
        $schoolId = $user->school_id;

        $counts = AttendanceSession::where('school_id', $schoolId)
            ->whereDate('school_date', $today)
            ->select('attendance_status', DB::raw('count(*) as total'))
            ->groupBy('attendance_status')
            ->pluck('total', 'attendance_status');

        $totalStudents = Student::where('school_id', $schoolId)->where('status', 'active')->count();

        $recentTaps = RawRfidEvent::whereHas('terminal', fn ($q) => $q->where('school_id', $schoolId))
            ->with(['student:id,name', 'terminal:id,terminal_code,type'])
            ->latest('event_time')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'student' => $e->student?->name,
                'uid' => $e->uid_text,
                'direction' => $e->event_type,
                'terminal' => $e->terminal?->terminal_code,
                'time' => $e->event_time->setTimezone($tz)->format('H:i:s'),
                'outcome' => $e->outcome,
            ]);

        $perClass = SchoolClass::where('school_id', $schoolId)
            ->where('status', 'active')
            ->withCount(['memberships as students_count' => fn ($q) => $q->whereNull('to_date')])
            ->orderBy('name')
            ->get()
            ->map(function ($class) use ($schoolId, $today) {
                $present = AttendanceSession::where('school_id', $schoolId)
                    ->whereDate('school_date', $today)
                    ->whereIn('student_id', $class->memberships()->whereNull('to_date')->select('student_id'))
                    ->whereIn('attendance_status', ['hadir', 'terlambat'])
                    ->count();

                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'students' => $class->students_count,
                    'present' => $present,
                ];
            });

        return Inertia::render('Dashboard', [
            'today' => $today,
            'counts' => [
                'hadir' => (int) ($counts['hadir'] ?? 0),
                'terlambat' => (int) ($counts['terlambat'] ?? 0),
                'tidak_hadir' => (int) ($counts['tidak_hadir'] ?? 0),
                'belum_masuk' => max(0, $totalStudents - (int) ($counts->sum())),
            ],
            'totalStudents' => $totalStudents,
            'perClass' => $perClass,
            'recentTaps' => $recentTaps,
        ]);
    }
}
