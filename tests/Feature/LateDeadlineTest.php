<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\Concentration;
use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\DailyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LateDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SMK Telat', 'code' => 'SMK-TELAT', 'timezone' => 'Asia/Jakarta']);

        $this->student = Student::create([
            'school_id' => $this->school->id, 'name' => 'Siswa Telat', 'status' => 'active',
        ]);
    }

    private function setupClassAndYear(): void
    {
        $year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active',
        ]);
        $grade = Grade::create(['school_id' => $this->school->id, 'name' => 'X', 'level' => 10]);
        $conc = Concentration::create(['school_id' => $this->school->id, 'name' => 'TKJ']);
        $class = SchoolClass::create([
            'school_id' => $this->school->id,
            'grade_id' => $grade->id, 'concentration_id' => $conc->id,
            'code' => 'X-TKJ-1', 'name' => 'X TKJ 1', 'status' => 'active',
        ]);
        $this->student->memberships()->create([
            'class_id' => $class->id, 'academic_year_id' => $year->id, 'from_date' => '2026-01-01',
        ]);
    }

    private function setSettings(?string $deadline): void
    {
        SchoolSetting::updateOrCreate(
            ['school_id' => $this->school->id],
            [
                'check_in_time' => '07:00:00',
                'check_out_time' => '15:00:00',
                'late_deadline_time' => $deadline,
                'weekly_off_days' => [],
            ]
        );
    }

    private function statusOn(string $wib): string
    {
        Carbon::setTestNow(Carbon::parse($wib, 'Asia/Jakarta'));
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $recap = app(DailyReportService::class)->recap($this->school, $today);

        return $recap['classes'][0]['students'][0]['status'];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_before_deadline_stays_belum_masuk(): void
    {
        $this->setupClassAndYear();
        $this->setSettings('07:30:00');

        $this->assertSame('belum_masuk', $this->statusOn('2026-11-30 07:15:00'));
    }

    public function test_after_deadline_before_checkout_is_terlambat(): void
    {
        $this->setupClassAndYear();
        $this->setSettings('07:30:00');

        $this->assertSame('terlambat', $this->statusOn('2026-11-30 08:00:00'));
    }

    public function test_after_checkout_is_tidak_hadir(): void
    {
        $this->setupClassAndYear();
        $this->setSettings('07:30:00');

        $this->assertSame('tidak_hadir', $this->statusOn('2026-11-30 16:00:00'));
    }

    public function test_null_deadline_keeps_old_behavior(): void
    {
        $this->setupClassAndYear();
        $this->setSettings(null);

        $this->assertSame('belum_masuk', $this->statusOn('2026-11-30 08:00:00'));
        $this->assertSame('tidak_hadir', $this->statusOn('2026-11-30 16:00:00'));
    }

    public function test_checked_in_student_not_overridden(): void
    {
        $this->setupClassAndYear();
        $this->setSettings('07:30:00');

        Carbon::setTestNow(Carbon::parse('2026-11-30 06:55:00', 'Asia/Jakarta'));
        AttendanceSession::create([
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'school_date' => '2026-11-30',
            'attendance_status' => 'hadir',
            'check_in_at' => '2026-11-29 23:55:00',
        ]);

        $this->assertSame('hadir', $this->statusOn('2026-11-30 09:00:00'));
    }

    public function test_admin_can_save_late_deadline(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@telat.test',
            'password' => Hash::make('password'),
            'school_id' => $this->school->id, 'role' => 'admin', 'status' => 'active',
        ]);

        $this->actingAs($admin)->put(route('settings.update'), [
            'check_in_time' => '07:00',
            'check_out_time' => '15:00',
            'late_deadline_time' => '07:30',
            'weekly_off_days' => [6, 7],
        ])->assertRedirect();

        $settings = SchoolSetting::where('school_id', $this->school->id)->first();
        $this->assertSame('07:30:00', $settings->late_deadline_time->format('H:i:s'));
    }

    public function test_deadline_must_be_between_in_and_out(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin2@telat.test',
            'password' => Hash::make('password'),
            'school_id' => $this->school->id, 'role' => 'admin', 'status' => 'active',
        ]);

        $this->actingAs($admin)->put(route('settings.update'), [
            'check_in_time' => '07:00',
            'check_out_time' => '15:00',
            'late_deadline_time' => '16:00',
            'weekly_off_days' => [6, 7],
        ])->assertSessionHasErrors('late_deadline_time');
    }
}
