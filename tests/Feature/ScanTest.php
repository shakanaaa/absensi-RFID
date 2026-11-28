<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Concentration;
use App\Models\Grade;
use App\Models\RfidCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentCardAssignment;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScanTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $piket;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SMK Scan', 'code' => 'SMK-SCAN', 'timezone' => 'Asia/Jakarta']);
        SchoolSetting::create([
            'school_id' => $this->school->id,
            'check_in_time' => '00:00:00',
            'check_out_time' => '23:59:00',
            'weekly_off_days' => [],
        ]);

        $this->admin = $this->user('admin@scan.test', 'admin');
        $this->piket = $this->user('piket@scan.test', 'petugas_piket');

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

        $this->student = Student::create([
            'school_id' => $this->school->id, 'name' => 'Siswa Scan', 'status' => 'active',
        ]);
        $this->student->memberships()->create([
            'class_id' => $class->id, 'academic_year_id' => $year->id, 'from_date' => '2026-01-01',
        ]);

        $card = RfidCard::create(['uid_text' => 'UID-SCAN-1', 'status' => 'active']);
        StudentCardAssignment::create([
            'rfid_card_id' => $card->id, 'student_id' => $this->student->id,
            'assigned_at' => now(), 'status' => 'active', 'active_flag' => 'ACTIVE',
        ]);
    }

    private function user(string $email, string $role): User
    {
        return User::create([
            'name' => $role, 'username' => $email, 'email' => $email,
            'password' => Hash::make('password'),
            'school_id' => $this->school->id, 'role' => $role, 'status' => 'active',
        ]);
    }

    private function scan(User $user, string $uid = 'UID-SCAN-1', string $direction = 'in')
    {
        return $this->actingAs($user)->postJson(route('scan.store'), [
            'uid_text' => $uid, 'direction' => $direction,
        ]);
    }

    public function test_admin_scan_in_mencreates_check_in(): void
    {
        $res = $this->scan($this->admin);

        $res->assertOk()
            ->assertJsonPath('student_name', 'Siswa Scan');
        $this->assertContains($res->json('outcome'), ['hadir', 'terlambat']);

        $session = \App\Models\AttendanceSession::where('student_id', $this->student->id)->first();
        $this->assertNotNull($session->check_in_at);
        $this->assertContains($session->attendance_status, ['hadir', 'terlambat']);

        // Terminal virtual WEB-IN otomatis dibuat.
        $this->assertDatabaseHas('terminals', [
            'school_id' => $this->school->id, 'terminal_code' => 'WEB-IN', 'type' => 'in',
        ]);
    }

    public function test_petugas_piket_can_scan(): void
    {
        $res = $this->scan($this->piket)->assertOk();
        $this->assertContains($res->json('outcome'), ['hadir', 'terlambat']);
    }

    public function test_scan_out_records_check_out(): void
    {
        $this->scan($this->admin);
        $this->scan($this->admin, 'UID-SCAN-1', 'out')
            ->assertOk()->assertJsonPath('outcome', 'pulang');

        $session = \App\Models\AttendanceSession::where('student_id', $this->student->id)->first();
        $this->assertNotNull($session->check_out_at);
    }

    public function test_unknown_card_returns_unknown_card(): void
    {
        $this->scan($this->admin, 'UID-TIDAK-ADA')
            ->assertOk()->assertJsonPath('outcome', 'unknown_card');
    }

    public function test_scan_page_loads(): void
    {
        $this->actingAs($this->admin)->get(route('scan.index'))->assertOk();
    }
}
