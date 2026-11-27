<?php

namespace App\Http\Controllers;

use App\Http\Requests\GradeRequest;
use App\Models\Grade;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Grade::class);

        $grades = Grade::where('school_id', $request->user()->school_id)
            ->withCount('classes')
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Master/Grades/Index', ['grades' => $grades]);
    }

    public function create(): Response
    {
        $this->authorize('create', Grade::class);

        return Inertia::render('Master/Grades/Form', ['grade' => null]);
    }

    public function store(GradeRequest $request): RedirectResponse
    {
        $grade = Grade::create($request->safe()->all() + ['school_id' => $request->user()->school_id]);

        AuditLogger::log('grade.created', Grade::class, $grade, after: $grade->only(['name', 'level']));

        return redirect()->route('master.grades.index')->with('success', 'Tingkat dibuat.');
    }

    public function edit(Grade $grade): Response
    {
        $this->authorize('update', $grade);

        return Inertia::render('Master/Grades/Form', ['grade' => $grade]);
    }

    public function update(GradeRequest $request, Grade $grade): RedirectResponse
    {
        $before = $grade->only(['name', 'level']);
        $grade->update($request->validated());

        AuditLogger::log('grade.updated', Grade::class, $grade, before: $before, after: $grade->only(['name', 'level']));

        return redirect()->route('master.grades.index')->with('success', 'Tingkat diperbarui.');
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        $this->authorize('delete', $grade);

        if ($grade->classes()->exists()) {
            return back()->with('error', 'Tingkat masih dipakai kelas. Nonaktifkan dengan mengosongkan kelas terlebih dahulu.');
        }

        $grade->delete();

        AuditLogger::log('grade.deleted', Grade::class, $grade->id);

        return redirect()->route('master.grades.index')->with('success', 'Tingkat dihapus.');
    }
}
