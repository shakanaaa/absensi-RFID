<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AcademicYear::class);

        $years = AcademicYear::where('school_id', $request->user()->school_id)
            ->withCount('memberships')
            ->orderByDesc('start_date')
            ->paginate(15);

        return Inertia::render('Master/AcademicYears/Index', [
            'years' => $years,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', AcademicYear::class);

        return Inertia::render('Master/AcademicYears/Form', ['year' => null]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $year = AcademicYear::create($request->safe()->except('status') + [
            'school_id' => $request->user()->school_id,
            'status' => $request->validated('status'),
        ]);

        if ($year->status === 'active') {
            $this->closeOtherActiveYears($request->user()->school_id, $year->id);
        }

        AuditLogger::log('academic_year.created', AcademicYear::class, $year, after: $year->only(['name', 'start_date', 'end_date', 'status']));

        return redirect()->route('master.academic-years.index')->with('success', 'Tahun ajaran dibuat.');
    }

    public function edit(AcademicYear $academicYear): Response
    {
        $this->authorize('update', $academicYear);

        return Inertia::render('Master/AcademicYears/Form', ['year' => $academicYear]);
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $before = $academicYear->only(['name', 'start_date', 'end_date', 'status']);
        $academicYear->update($request->validated());

        if ($academicYear->status === 'active') {
            $this->closeOtherActiveYears($academicYear->school_id, $academicYear->id);
        }

        AuditLogger::log('academic_year.updated', AcademicYear::class, $academicYear, before: $before, after: $academicYear->only(['name', 'start_date', 'end_date', 'status']));

        return redirect()->route('master.academic-years.index')->with('success', 'Tahun ajaran diperbarui.');
    }

    public function destroy(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('delete', $academicYear);

        // Tahun aktif tidak boleh diarsipkan — tutup dulu lewat form ubah.
        if ($academicYear->status === 'active') {
            return back()->with('error', 'Tahun ajaran aktif tidak bisa diarsipkan. Ubah statusnya dulu.');
        }

        // Jangan hapus data yang sudah dipakai — arsipkan (PRD FR-MST-001).
        $academicYear->update(['status' => 'archived']);

        AuditLogger::log('academic_year.archived', AcademicYear::class, $academicYear);

        return redirect()->route('master.academic-years.index')->with('success', 'Tahun ajaran diarsipkan.');
    }

    /** Maksimal satu tahun ajaran aktif per sekolah. */
    private function closeOtherActiveYears(int $schoolId, int $exceptId): void
    {
        AcademicYear::where('school_id', $schoolId)
            ->where('id', '!=', $exceptId)
            ->where('status', 'active')
            ->update(['status' => 'closed']);
    }
}
