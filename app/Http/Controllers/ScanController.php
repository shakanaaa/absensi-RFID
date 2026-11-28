<?php

namespace App\Http\Controllers;

use App\Models\RawRfidEvent;
use App\Models\Terminal;
use App\Services\AttendanceEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Absensi manual via reader RFID keyboard-wedge di browser.
 * Reader "mengetik" UID + Enter ke input halaman ini; event diproses
 * lewat pipeline yang sama dengan terminal (RawRfidEvent → evaluator)
 * memakai terminal virtual WEB-IN / WEB-OUT per sekolah.
 */
class ScanController extends Controller
{
    public function __construct(private AttendanceEvaluator $evaluator) {}

    public function index(Request $request): Response
    {
        $schoolId = $request->user()->school_id;

        $terminals = Terminal::where('school_id', $schoolId)
            ->whereIn('terminal_code', ['WEB-IN', 'WEB-OUT'])
            ->pluck('id');

        $recent = $terminals->isEmpty()
            ? collect()
            : RawRfidEvent::whereIn('terminal_id', $terminals)
                ->with('student:id,name')
                ->latest('event_time')
                ->limit(15)
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'uid_text' => $e->uid_text,
                    'student_name' => $e->student?->name,
                    'direction' => $e->event_type === 'exit' ? 'out' : 'in',
                    'outcome' => $e->outcome,
                    'event_time' => $e->event_time->setTimezone($request->user()->school?->timezone ?: config('app.timezone'))->format('H:i:s'),
                ]);

        return Inertia::render('Attendance/Scan', [
            'recent' => $recent,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uid_text' => ['required', 'string', 'max:64'],
            'direction' => ['required', 'in:in,out'],
        ]);

        $schoolId = $request->user()->school_id;
        $type = $data['direction'];

        $terminal = Terminal::firstOrCreate(
            ['school_id' => $schoolId, 'terminal_code' => 'WEB-'.strtoupper($type)],
            [
                'type' => $type,
                'location' => 'Scan via browser',
                'status' => 'active',
            ]
        );

        $event = RawRfidEvent::create([
            'event_id' => (string) Str::uuid(),
            'terminal_id' => $terminal->id,
            'uid_text' => trim($data['uid_text']),
            'event_type' => $type === 'out' ? 'exit' : 'entry',
            'event_time' => now(),
            'received_at' => now(),
            'time_quality' => 'ok',
        ]);

        $outcome = $this->evaluator->evaluate($event);
        $event->load('student:id,name');

        return response()->json([
            'outcome' => $outcome,
            'student_name' => $event->student?->name,
            'direction' => $type,
        ]);
    }
}
