<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\DailyReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan absensi harian + tautan kirim ke WhatsApp (wa.me).
 */
class ReportController extends Controller
{
    public function index(Request $request, DailyReportService $reports): Response
    {
        $user = $request->user();
        $school = School::with('settings')->findOrFail($user->school_id);
        $tz = $school->timezone ?: config('app.timezone');

        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : now($tz)->toDateString();

        $recap = $reports->recap($school, $date);

        // Filter per kelas untuk tampilan detail.
        $classId = $request->integer('class_id') ?: null;
        $classes = collect($recap['classes'])
            ->when($classId, fn ($c) => $c->where('class_id', $classId)->values())
            ->values();

        return Inertia::render('Reports/Index', [
            'date' => $date,
            'isSchoolDay' => $recap['is_school_day'],
            'classes' => $classes,
            'whatsappUrl' => $reports->whatsappUrl($school, $date, $recap),
            'whatsappConfigured' => filled($school->settings?->whatsapp_number),
        ]);
    }
}
