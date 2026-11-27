<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kenaikan kelas sederhana. Kelas adalah entitas permanen per sekolah
 * (tidak terikat tahun ajaran); tahun ajaran hanya tercatat pada
 * student_class_memberships.
 *
 *  - prepareYear: tutup tahun aktif, buat tahun baru (default 10 Jul–9 Jun).
 *  - promoteAll: pindahkan membership aktif dari tahun closed ke kelas
 *    setara di tahun aktif (grade level +1, urutan kode sama).
 *    Siswa di grade tertinggi → status 'lulus'.
 */
class PromotionService
{
    /** Buat tahun ajaran baru (aktif) dan tutup tahun aktif lama. */
    public function prepareYear(int $schoolId, ?string $name, ?string $startDate, ?string $endDate, ?User $actor = null): AcademicYear
    {
        return DB::transaction(function () use ($schoolId, $name, $startDate, $endDate) {
            $source = AcademicYear::where('school_id', $schoolId)->where('status', 'active')->firstOrFail();

            [$startDate, $endDate] = ($startDate && $endDate)
                ? [$startDate, $endDate]
                : self::defaultYearDates($source);

            $name ??= self::defaultYearName($startDate);

            $year = AcademicYear::create([
                'school_id' => $schoolId,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
            ]);

            // Nonaktifkan tahun lama agar hanya satu yang aktif.
            AcademicYear::where('school_id', $schoolId)
                ->where('id', '!=', $year->id)
                ->where('status', 'active')
                ->update(['status' => 'closed']);

            return $year;
        });
    }

    /** Periode default tahun ajaran baru: 10 Juli – 9 Juni, tahun setelah tahun sumber berakhir. */
    public static function defaultYearDates(AcademicYear $source): array
    {
        $y = $source->end_date->year;

        return ["{$y}-07-10", ($y + 1).'-06-09'];
    }

    public static function defaultYearName(string $startDate): string
    {
        $y = (int) substr($startDate, 0, 4);

        return "{$y}/".($y + 1);
    }

    /**
     * Promosikan semua siswa dari tahun ajaran closed ke tahun aktif.
     * Mengembalikan ringkasan ['promoted' => n, 'lulus' => n].
     */
    public function promoteAll(int $schoolId): array
    {
        return DB::transaction(function () use ($schoolId) {
            $targetYear = AcademicYear::where('school_id', $schoolId)->where('status', 'active')->first();

            abort_if(! $targetYear, 422, 'Belum ada tahun ajaran aktif untuk tujuan promosi.');

            // Tahun sumber = tahun ajaran closed terbaru.
            $sourceYear = AcademicYear::where('school_id', $schoolId)
                ->where('status', 'closed')->latest('end_date')->first();

            abort_if(! $sourceYear, 422, 'Tidak ada tahun ajaran sebelumnya untuk dipromosikan.');

            $promoted = 0;
            $lulus = 0;

            $memberships = StudentClassMembership::where('academic_year_id', $sourceYear->id)
                ->whereNull('to_date')
                ->with(['student', 'class.grade'])
                ->get();

            foreach ($memberships as $membership) {
                $student = $membership->student;
                $class = $membership->class;
                if (! $student || $student->status !== 'active' || ! $class) {
                    continue;
                }

                $target = $this->targetClass($schoolId, $class);

                // Tutup membership lama.
                $membership->update(['to_date' => $sourceYear->end_date]);

                if (! $target) {
                    // Grade tertinggi → lulus.
                    $student->update(['status' => 'lulus']);
                    $lulus++;
                    continue;
                }

                StudentClassMembership::create([
                    'student_id' => $student->id,
                    'class_id' => $target->id,
                    'academic_year_id' => $targetYear->id,
                    'from_date' => $targetYear->start_date,
                    'attendance_number' => $membership->attendance_number,
                ]);
                $promoted++;
            }

            return ['promoted' => $promoted, 'lulus' => $lulus];
        });
    }

    /**
     * Kelas tujuan: grade berikutnya (level+1), urutan indeks sama.
     * Null hanya bila grade sumber sudah tertinggi (→ siswa lulus).
     * Lempar ValidationException bila grade berikutnya ada tapi kelasnya belum dibuat.
     */
    private function targetClass(int $schoolId, SchoolClass $sourceClass): ?SchoolClass
    {
        $nextGradeId = $this->nextGradeId($schoolId, $sourceClass->grade_id);
        if (! $nextGradeId) {
            return null;
        }

        // Konsentrasi ikut kelas — target harus konsentrasi yang sama.
        $candidates = SchoolClass::where('school_id', $schoolId)
            ->where('grade_id', $nextGradeId)
            ->where('concentration_id', $sourceClass->concentration_id)
            ->where('status', 'active')
            ->orderBy('code')
            ->get();

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages([
                'class' => "Belum ada kelas untuk grade lanjutan dari {$sourceClass->name}. Buat dulu kelasnya.",
            ]);
        }

        // Cocokkan berdasarkan indeks numerik di akhir kode (X-1 → XI-1).
        $index = $this->classIndex($sourceClass->code);
        if ($index !== null) {
            $match = $candidates->first(fn ($c) => $this->classIndex($c->code) === $index);
            if ($match) {
                return $match;
            }
        }

        // Fallback: posisi ke-N di antara kelas satu grade & konsentrasi.
        $sourceSiblings = SchoolClass::where('school_id', $schoolId)
            ->where('grade_id', $sourceClass->grade_id)
            ->where('concentration_id', $sourceClass->concentration_id)
            ->orderBy('code')->pluck('id');
        $pos = $sourceSiblings->search($sourceClass->id);

        return $candidates->values()->get($pos === false ? 0 : $pos) ?? $candidates->first();
    }

    /** Grade dengan level +1 dari grade sumber; null bila sudah tertinggi. */
    private function nextGradeId(int $schoolId, ?int $gradeId): ?int
    {
        $grade = $gradeId ? Grade::find($gradeId) : null;
        if (! $grade || $grade->level === null) {
            return null;
        }

        return Grade::where('school_id', $schoolId)
            ->where('level', $grade->level + 1)
            ->value('id');
    }

    /** Angka urut di akhir kode kelas ('X-1' → 1, 'XII-A' → null). */
    private function classIndex(string $code): ?int
    {
        return preg_match('/(\d+)\s*$/', $code, $m) ? (int) $m[1] : null;
    }
}
