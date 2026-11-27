<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolHoliday;
use App\Models\SchoolSetting;
use App\Services\AuditLogger;
use App\Services\HolidayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan operasional sekolah: jam absensi, nomor WA laporan,
 * dan kalender libur (manual + generate libur nasional).
 */
class SettingsController extends Controller
{
    public function index(Request $request, HolidayService $holidays): Response
    {
        $this->authorizeSettings($request);

        $school = School::with('settings')->findOrFail($request->user()->school_id);

        $settings = $school->settings ?? new SchoolSetting(['school_id' => $school->id]);

        $year = $request->integer('year') ?: (int) now()->format('Y');

        return Inertia::render('Settings/Index', [
            'settings' => [
                'check_in_time' => $settings->check_in_time?->format('H:i'),
                'check_out_time' => $settings->check_out_time?->format('H:i'),
                'whatsapp_number' => $settings->whatsapp_number,
                'report_send_time' => $settings->report_send_time?->format('H:i'),
            ],
            'holidays' => SchoolHoliday::where('school_id', $school->id)
                ->orderBy('holiday_date')->get(['id', 'holiday_date', 'name']),
            'holidayYear' => $year,
            // Preview libur nasional yang akan di-generate untuk tahun tsb.
            'nationalHolidays' => collect($holidays->holidaysForYear($year))
                ->map(fn ($name, $date) => ['date' => $date, 'name' => $name])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);

        $data = $request->validate([
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i', 'after:check_in_time'],
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'report_send_time' => ['nullable', 'date_format:H:i'],
        ]);

        $school = School::findOrFail($request->user()->school_id);

        SchoolSetting::updateOrCreate(
            ['school_id' => $school->id],
            [
                'check_in_time' => $data['check_in_time'].':00',
                'check_out_time' => $data['check_out_time'].':00',
                'whatsapp_number' => $data['whatsapp_number'] ?? null,
                'report_send_time' => isset($data['report_send_time']) ? $data['report_send_time'].':00' : null,
            ]
        );

        AuditLogger::log('settings.updated', SchoolSetting::class, $school->id, after: $data);

        return back()->with('success', 'Pengaturan disimpan.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);

        $data = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        SchoolHoliday::updateOrCreate(
            ['school_id' => $request->user()->school_id, 'holiday_date' => $data['holiday_date']],
            ['name' => $data['name']]
        );

        return back()->with('success', 'Libur ditambahkan.');
    }

    public function destroyHoliday(Request $request, SchoolHoliday $holiday): RedirectResponse
    {
        $this->authorizeSettings($request);
        abort_if($holiday->school_id !== $request->user()->school_id, 404);

        $holiday->delete();

        return back()->with('success', 'Libur dihapus.');
    }

    /** Isi school_holidays dengan libur nasional tahun tertentu (idempotent). */
    public function generateHolidays(Request $request, HolidayService $holidays): RedirectResponse
    {
        $this->authorizeSettings($request);

        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100']]);
        $year = (int) $data['year'];

        $created = 0;
        foreach ($holidays->holidaysForYear($year) as $date => $name) {
            $created += SchoolHoliday::updateOrCreate(
                ['school_id' => $request->user()->school_id, 'holiday_date' => $date],
                ['name' => $name]
            )->wasRecentlyCreated ? 1 : 0;
        }

        AuditLogger::log('settings.holidays_generated', SchoolHoliday::class, $request->user()->school_id,
            after: ['year' => $year, 'created' => $created]);

        return back()->with('success', "Libur nasional {$year}: {$created} tanggal baru ditambahkan.");
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }
}
