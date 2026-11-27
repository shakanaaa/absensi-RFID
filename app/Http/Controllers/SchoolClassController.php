<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolClassRequest;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SchoolClass::class);

        $query = SchoolClass::where('school_id', $request->user()->school_id)
            ->with(['grade:id,name', 'concentration:id,name'])
            ->withCount(['memberships as students_count' => fn ($q) => $q->whereNull('to_date')]);

        $classes = $query->orderBy('code')->paginate(20);

        return Inertia::render('Master/Classes/Index', ['classes' => $classes]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', SchoolClass::class);

        return Inertia::render('Master/Classes/Form', [
            'classData' => null,
            'grades' => $this->grades($request),
            'concentrations' => $this->concentrations($request),
        ]);
    }

    public function store(SchoolClassRequest $request): RedirectResponse
    {
        $class = SchoolClass::create($request->safe()->all() + ['school_id' => $request->user()->school_id]);

        AuditLogger::log('class.created', SchoolClass::class, $class, after: $class->only(['code', 'name', 'grade_id', 'concentration_id']));

        return redirect()->route('master.classes.index')->with('success', 'Kelas dibuat.');
    }

    public function edit(Request $request, SchoolClass $class): Response
    {
        $this->authorize('update', $class);

        return Inertia::render('Master/Classes/Form', [
            'classData' => $class->load(['grade:id,name', 'concentration:id,name']),
            'grades' => $this->grades($request),
            'concentrations' => $this->concentrations($request),
        ]);
    }

    public function update(SchoolClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $before = $class->only(['code', 'name', 'grade_id', 'concentration_id', 'status']);
        $class->update($request->validated());

        AuditLogger::log('class.updated', SchoolClass::class, $class, before: $before, after: $class->only(['code', 'name', 'grade_id', 'concentration_id', 'status']));

        return redirect()->route('master.classes.index')->with('success', 'Kelas diperbarui.');
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        $this->authorize('delete', $class);

        if ($class->memberships()->exists()) {
            $class->update(['status' => 'archived']);
            AuditLogger::log('class.archived', SchoolClass::class, $class);

            return redirect()->route('master.classes.index')->with('success', 'Kelas punya riwayat anggota, diarsipkan.');
        }

        $class->delete();
        AuditLogger::log('class.deleted', SchoolClass::class, $class->id);

        return redirect()->route('master.classes.index')->with('success', 'Kelas dihapus.');
    }

    private function grades(Request $request)
    {
        return Grade::where('school_id', $request->user()->school_id)->orderBy('level')->orderBy('name')->get(['id', 'name']);
    }

    private function concentrations(Request $request)
    {
        return \App\Models\Concentration::where('school_id', $request->user()->school_id)->orderBy('name')->get(['id', 'name']);
    }
}
