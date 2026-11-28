<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassMembership;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Student::class);

        $query = Student::where('school_id', $request->user()->school_id)
            ->with(['memberships' => fn ($q) => $q->whereNull('to_date')->with('class:id,name,code')]);

        if ($request->filled('q')) {
            $q = $request->string('q')->trim()->toString();
            $query->where('name', 'like', "%{$q}%");
        }

        if ($request->filled('class_id')) {
            $query->whereHas('memberships', fn ($m) => $m->where('class_id', $request->integer('class_id'))->whereNull('to_date'));
        }

        $students = $query->orderBy('name')->paginate(20)->withQueryString();

        $classes = SchoolClass::where('school_id', $request->user()->school_id)
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'name', 'code']);

        return Inertia::render('Master/Students/Index', [
            'students' => $students,
            'classes' => $classes,
            'filters' => $request->only(['q', 'class_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Student::class);

        // Smart default: tahun ajaran aktif (fallback terbaru non-arsip) + hari ini.
        $defaultYear = AcademicYear::where('school_id', $request->user()->school_id)
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->value('id')
            ?? AcademicYear::where('school_id', $request->user()->school_id)
                ->where('status', '!=', 'archived')
                ->orderByDesc('start_date')
                ->value('id');

        return Inertia::render('Master/Students/Form', [
            'student' => null,
            'classes' => $this->classes($request),
            'years' => $this->years($request),
            'defaults' => ['academic_year_id' => $defaultYear],
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['class_id', 'academic_year_id', 'attendance_number']);

        $student = DB::transaction(function () use ($request, $data) {
            $student = Student::create($data + ['school_id' => $request->user()->school_id]);
            $this->syncMembership($student, $request->validated());

            return $student;
        });

        AuditLogger::log('student.created', Student::class, $student, after: $student->only(['name', 'status']));

        return redirect()->route('master.students.show', $student)->with('success', 'Siswa ditambahkan.');
    }

    public function show(Request $request, Student $student): Response
    {
        $this->authorize('view', $student);

        $student->load([
            'memberships' => fn ($q) => $q->with('class:id,name,code', 'academicYear:id,name')->orderByDesc('from_date'),
            'cardAssignments.card:id,uid_text,status',
            'activeCardAssignment.card:id,uid_text,status',
        ]);

        return Inertia::render('Master/Students/Show', [
            'student' => $student,
            'classes' => $this->classes($request),
            'years' => $this->years($request),
        ]);
    }

    public function edit(Request $request, Student $student): Response
    {
        $this->authorize('update', $student);

        $student->load(['memberships' => fn ($q) => $q->whereNull('to_date')->with('class:id,name,code', 'academicYear:id,name')]);

        return Inertia::render('Master/Students/Form', [
            'student' => $student,
            'classes' => $this->classes($request),
            'years' => $this->years($request),
        ]);
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $data = $request->safe()->except(['class_id', 'academic_year_id', 'attendance_number']);

        DB::transaction(function () use ($request, $student, $data) {
            $before = $student->only(['name', 'gender', 'status']);
            $student->update($data);
            $this->syncMembership($student, $request->validated());

            // Status non-aktif → tutup membership & lepas kartu agar tidak menggantung.
            if (in_array($student->status, ['inactive', 'lulus'], true) && $before['status'] === 'active') {
                $student->memberships()->whereNull('to_date')
                    ->update(['to_date' => now()->toDateString()]);

                $assignment = $student->activeCardAssignment;
                if ($assignment) {
                    app(\App\Services\CardService::class)
                        ->unassign($assignment, null, 'Status siswa: '.$student->status.'.');
                }
            }

            AuditLogger::log('student.updated', Student::class, $student, before: $before, after: $student->only(['name', 'gender', 'status']));
        });

        return redirect()->route('master.students.show', $student)->with('success', 'Siswa diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->authorize('delete', $student);

        // Jangan hapus siswa yang punya histori — nonaktifkan (PRD FR-MST-002).
        // Membership aktif ditutup dan kartu dilepas agar tidak menggantung.
        DB::transaction(function () use ($student) {
            $student->memberships()->whereNull('to_date')
                ->update(['to_date' => now()->toDateString()]);

            $assignment = $student->activeCardAssignment;
            if ($assignment) {
                app(\App\Services\CardService::class)
                    ->unassign($assignment, null, 'Siswa dinonaktifkan.');
            }

            $student->update(['status' => 'inactive']);
        });

        AuditLogger::log('student.deactivated', Student::class, $student);

        return redirect()->route('master.students.index')->with('success', 'Siswa dinonaktifkan.');
    }

    /**
     * Sinkronkan keanggotaan kelas efektif. from_date otomatis = hari ini;
     * to_date hanya diisi sistem saat membership ditutup (pindah kelas,
     * promosi, nonaktif) — bukan input manual.
     */
    private function syncMembership(Student $student, array $data): void
    {
        $active = StudentClassMembership::where('student_id', $student->id)
            ->whereNull('to_date')
            ->first();

        $sameTarget = $active
            && $active->class_id === (int) $data['class_id']
            && $active->academic_year_id === (int) $data['academic_year_id'];

        if ($sameTarget) {
            $active->update([
                'attendance_number' => array_key_exists('attendance_number', $data)
                    ? $data['attendance_number']
                    : $active->attendance_number,
            ]);

            return;
        }

        $fromDate = now()->toDateString();

        if ($active) {
            // Tutup membership lama sehari sebelum yang baru mulai.
            $toDate = \Carbon\Carbon::parse($fromDate)->subDay()->toDateString();
            if ($toDate < $active->from_date->toDateString()) {
                $toDate = $active->from_date->toDateString();
            }
            $active->update(['to_date' => $toDate]);
        }

        StudentClassMembership::create([
            'student_id' => $student->id,
            'class_id' => $data['class_id'],
            'academic_year_id' => $data['academic_year_id'],
            'from_date' => $fromDate,
            'attendance_number' => $data['attendance_number'] ?? null,
        ]);
    }

    private function classes(Request $request)
    {
        return SchoolClass::where('school_id', $request->user()->school_id)
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'name', 'code']);
    }

    private function years(Request $request)
    {
        return AcademicYear::where('school_id', $request->user()->school_id)
            ->where('status', '!=', 'archived')
            ->orderByDesc('start_date')
            ->get(['id', 'name']);
    }
}
