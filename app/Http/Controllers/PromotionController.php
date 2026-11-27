<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kenaikan kelas: admin menyiapkan tahun ajaran baru lalu mempromosikan
 * seluruh siswa aktif. Siswa grade tertinggi → status lulus.
 */
class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        $schoolId = $request->user()->school_id;

        $years = AcademicYear::where('school_id', $schoolId)
            ->orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date', 'status']);

        $activeYear = $years->firstWhere('status', 'active');

        // Kelas yang punya anggota aktif di tahun ajaran tertutup terbaru.
        $sourceYear = AcademicYear::where('school_id', $schoolId)
            ->where('status', 'closed')->latest('end_date')->first();

        $sourceClasses = $sourceYear
            ? SchoolClass::where('school_id', $schoolId)
                ->where('status', 'active')
                ->whereHas('memberships', fn ($m) => $m
                    ->where('academic_year_id', $sourceYear->id)
                    ->whereNull('to_date'))
                ->with('grade:id,name,level')
                ->withCount(['memberships as students_count' => fn ($q) => $q
                    ->where('academic_year_id', $sourceYear->id)
                    ->whereNull('to_date')
                    ->whereHas('student', fn ($s) => $s->where('status', 'active'))])
                ->orderBy('code')->get()
            : collect();

        $lulusCount = Student::where('school_id', $schoolId)->where('status', 'lulus')->count();

        // Pratinjau periode otomatis untuk tahun ajaran baru (10 Jul – 9 Jun).
        $nextYear = $activeYear ? PromotionService::defaultYearDates($activeYear) : null;

        return Inertia::render('Promotion/Index', [
            'years' => $years,
            'activeYear' => $activeYear,
            'sourceYear' => $sourceYear,
            'sourceClasses' => $sourceClasses,
            'lulusCount' => $lulusCount,
            'canPromote' => $activeYear && $sourceYear,
            'nextYearPreview' => $nextYear ? [
                'name' => PromotionService::defaultYearName($nextYear[0]),
                'start_date' => $nextYear[0],
                'end_date' => $nextYear[1],
            ] : null,
        ]);
    }

    /** Buat tahun ajaran baru + skeleton kelas (grade sudah digeser). */
    public function prepareYear(Request $request, PromotionService $promotion): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:50'],
        ]);

        $year = $promotion->prepareYear(
            $request->user()->school_id,
            $data['name'] ?? null,
            null,
            null,
            $request->user()
        );

        AuditLogger::log('promotion.year_prepared', AcademicYear::class, $year,
            after: $year->only(['name', 'start_date', 'end_date', 'status']));

        return back()->with('success', "Tahun ajaran {$year->name} dibuat, tahun sebelumnya ditutup.");
    }

    /** Jalankan promosi semua siswa dari tahun sumber ke tahun aktif. */
    public function promote(Request $request, PromotionService $promotion): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $result = $promotion->promoteAll($request->user()->school_id);

        AuditLogger::log('promotion.executed', AcademicYear::class, null,
            after: $result, actor: $request->user());

        return back()->with('success',
            "Promosi selesai: {$result['promoted']} siswa naik kelas, {$result['lulus']} siswa lulus.");
    }
}
