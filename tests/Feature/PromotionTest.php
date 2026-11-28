<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Concentration;
use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassMembership;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $piket;
    private AcademicYear $yearX;
    private Concentration $conc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SMA Promo', 'code' => 'SMA-PROMO', 'timezone' => 'Asia/Jakarta']);
        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin-promo', 'email' => 'admin@promo.test', 'password' => Hash::make('password'),
            'school_id' => $this->school->id, 'role' => 'admin', 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $this->piket = User::create([
            'name' => 'Piket', 'username' => 'piket-promo', 'email' => 'piket@promo.test', 'password' => Hash::make('password'),
            'school_id' => $this->school->id, 'role' => 'petugas_piket', 'status' => 'active', 'email_verified_at' => now(),
        ]);

        $this->yearX = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2025/2026',
            'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'status' => 'active',
        ]);

        foreach ([10 => 'X', 11 => 'XI', 12 => 'XII'] as $level => $name) {
            Grade::create(['school_id' => $this->school->id, 'name' => $name, 'level' => $level]);
        }

        $this->conc = Concentration::create(['school_id' => $this->school->id, 'name' => 'IPA']);
    }

    private function classIn(string $gradeName, string $code, string $name): SchoolClass
    {
        return SchoolClass::create([
            'school_id' => $this->school->id,
            'grade_id' => Grade::where('school_id', $this->school->id)->where('name', $gradeName)->value('id'),
            'concentration_id' => $this->conc->id,
            'code' => $code, 'name' => $name, 'status' => 'active',
        ]);
    }

    private function studentIn(SchoolClass $class, AcademicYear $year, string $status = 'active'): Student
    {
        static $n = 0;
        $n++;
        $student = Student::create([
            'school_id' => $this->school->id, 'name' => 'Siswa '.$n, 'status' => $status,
        ]);
        StudentClassMembership::create([
            'student_id' => $student->id, 'class_id' => $class->id,
            'academic_year_id' => $year->id, 'from_date' => $year->start_date->toDateString(),
        ]);

        return $student;
    }

    public function test_prepare_year_closes_old_and_creates_new_active(): void
    {
        $this->classIn('X', 'X-1', 'X IPA 1');
        $this->classIn('XII', 'XII-1', 'XII IPA 1');

        $this->actingAs($this->admin)->post(route('promotion.prepare-year'), [
            'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
        ])->assertRedirect();

        $newYear = AcademicYear::where('school_id', $this->school->id)->where('status', 'active')->firstOrFail();
        $this->assertEquals('closed', $this->yearX->fresh()->status);
        $this->assertEquals('2026/2027', $newYear->name);

        // Kelas permanen — tidak ada skeleton baru yang disalin.
        $this->assertEquals(2, SchoolClass::where('school_id', $this->school->id)->count());
    }

    public function test_prepare_year_auto_dates_july_to_june(): void
    {
        app(PromotionService::class)->prepareYear(
            $this->school->id, null, null, null
        );

        $year = AcademicYear::where('school_id', $this->school->id)->where('status', 'active')->firstOrFail();
        // Sumber berakhir 2026-06-30 → default 2026-07-10 s.d. 2027-06-09.
        $this->assertEquals('2026-07-10', $year->start_date->toDateString());
        $this->assertEquals('2027-06-09', $year->end_date->toDateString());
        $this->assertEquals('2026/2027', $year->name);
    }

    public function test_promote_moves_students_and_graduates_top_grade(): void
    {
        $classX = $this->classIn('X', 'X-1', 'X IPA 1');
        $classXI = $this->classIn('XI', 'XI-1', 'XI IPA 1');
        $classXII = $this->classIn('XII', 'XII-1', 'XII IPA 1');

        $siswaX = $this->studentIn($classX, $this->yearX);
        $siswaXII = $this->studentIn($classXII, $this->yearX);

        app(PromotionService::class)->prepareYear(
            $this->school->id, '2026/2027', '2026-07-01', '2027-06-30'
        );

        $result = app(PromotionService::class)->promoteAll($this->school->id);

        $this->assertEquals(1, $result['promoted']);
        $this->assertEquals(1, $result['lulus']);

        // Siswa X pindah ke kelas XI-1 (kelas permanen, indeks sama) di tahun baru.
        $newMembership = StudentClassMembership::where('student_id', $siswaX->id)->whereNull('to_date')->firstOrFail();
        $this->assertEquals($classXI->id, $newMembership->class_id);
        $this->assertEquals('active', $siswaX->fresh()->status);

        // Siswa XII → lulus, membership lama tertutup.
        $this->assertEquals('lulus', $siswaXII->fresh()->status);
        $this->assertNotNull(
            StudentClassMembership::where('student_id', $siswaXII->id)->whereNotNull('to_date')->first()
        );
    }

    public function test_piket_cannot_access_promotion(): void
    {
        $this->actingAs($this->piket)->get(route('promotion.index'))->assertForbidden();
        $this->actingAs($this->piket)->post(route('promotion.promote'))->assertForbidden();
    }
}
