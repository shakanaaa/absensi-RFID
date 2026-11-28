<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolHoliday;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $piket;
    private SchoolClass $class;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SMA Rep', 'code' => 'SMA-REP', 'timezone' => 'Asia/Jakarta']);
        SchoolSetting::create([
            'school_id' => $this->school->id,
            'check_in_time' => '07:00:00',
            'check_out_time' => '15:00:00',
            'whatsapp_number' => '6281234567890',
        ]);

        $this->admin = $this->user('admin@rep.test', 'admin');
        $this->piket = $this->user('piket@rep.test', 'petugas_piket');

        $year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active',
        ]);
        $grade = Grade::create(['school_id' => $this->school->id, 'name' => 'X', 'level' => 10]);
        $conc = \App\Models\Concentration::create(['school_id' => $this->school->id, 'name' => 'IPA']);
        $this->class = SchoolClass::create([
            'school_id' => $this->school->id,
            'grade_id' => $grade->id, 'concentration_id' => $conc->id, 'code' => 'X-1', 'name' => 'X IPA 1',
            'status' => 'active',
        ]);
        $this->student = Student::create([
            'school_id' => $this->school->id, 'name' => 'Siswa Satu', 'status' => 'active',
        ]);
        StudentClassMembership::create([
            'student_id' => $this->student->id, 'class_id' => $this->class->id,
            'academic_year_id' => $year->id, 'from_date' => '2026-01-01',
        ]);
    }

    private function user(string $email, string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).$n, 'email' => $email,
            'password' => Hash::make('password'),
            'school_id' => $this->school->id,
            'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function makeSession(string $date, string $status, ?string $in = null, ?string $out = null): void
    {
        AttendanceSession::create([
            'student_id' => $this->student->id, 'school_id' => $this->school->id,
            'school_date' => $date, 'attendance_status' => $status,
            'check_in_at' => $in, 'check_out_at' => $out,
        ]);
    }

    public function test_daily_report_counts_and_whatsapp_url(): void
    {
        // 2026-11-25 Rabu — hari efektif.
        $this->makeSession('2026-11-25', 'hadir', '2026-11-25 06:55:00', '2026-11-25 15:05:00');

        $res = $this->actingAs($this->admin)->get(route('reports.index', ['date' => '2026-11-25']));
        $res->assertOk();

        $props = $res->viewData('page')['props'];
        $this->assertTrue($props['isSchoolDay']);
        $this->assertNotNull($props['whatsappUrl']);
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $props['whatsappUrl']);
        $this->assertStringContainsString('hadir', strtolower(urldecode($props['whatsappUrl'])));
    }

    public function test_report_marks_holiday_as_non_school_day(): void
    {
        SchoolHoliday::create([
            'school_id' => $this->school->id, 'holiday_date' => '2026-11-25', 'name' => 'Libur',
        ]);

        $res = $this->actingAs($this->admin)->get(route('reports.index', ['date' => '2026-11-25']));
        $this->assertFalse($res->viewData('page')['props']['isSchoolDay']);
    }

    public function test_whatsapp_button_disabled_without_number(): void
    {
        $this->school->settings()->update(['whatsapp_number' => null]);

        $res = $this->actingAs($this->admin)->get(route('reports.index', ['date' => '2026-11-25']));
        $props = $res->viewData('page')['props'];

        $this->assertFalse($props['whatsappConfigured']);
        $this->assertNull($props['whatsappUrl']);
    }

    public function test_attendance_page_shows_student_statuses(): void
    {
        // check_in_at disimpan UTC; 00:30 UTC = 07:30 WIB.
        $this->makeSession('2026-11-25', 'terlambat', '2026-11-25 00:30:00');

        $res = $this->actingAs($this->piket)->get(route('attendance.index', [
            'date' => '2026-11-25', 'class_id' => $this->class->id,
        ]));
        $res->assertOk();

        $students = $res->viewData('page')['props']['students'];
        $this->assertCount(1, $students);
        $this->assertEquals('terlambat', $students[0]['status']);
        $this->assertEquals('07:30', $students[0]['check_in_at']);
    }

    public function test_piket_can_view_attendance_and_reports(): void
    {
        $this->actingAs($this->piket)->get(route('attendance.index'))->assertOk();
        $this->actingAs($this->piket)->get(route('reports.index'))->assertOk();
        $this->actingAs($this->piket)->get(route('dashboard'))->assertOk();
    }

    public function test_dashboard_renders_for_staff(): void
    {
        $res = $this->actingAs($this->admin)->get(route('dashboard'));
        $res->assertOk();
        $this->assertArrayHasKey('counts', $res->viewData('page')['props']);
    }

    public function test_whatsapp_text_only_includes_selected_sections(): void
    {
        $s2 = Student::create(['school_id' => $this->school->id, 'name' => 'Siswa Dua', 'status' => 'active']);
        StudentClassMembership::create([
            'student_id' => $s2->id, 'class_id' => $this->class->id,
            'academic_year_id' => $this->student->memberships()->first()->academic_year_id,
            'from_date' => '2026-01-01',
        ]);

        // Siswa 1 terlambat; siswa 2 tanpa sesi → tidak_hadir (tanggal lampau).
        $this->makeSession('2026-11-25', 'terlambat', '2026-11-25 00:30:00');

        $this->school->settings()->update([
            'wa_report_options' => [
                'counts' => false, 'hadir' => false,
                'terlambat' => true, 'tidak_hadir' => true, 'belum_masuk' => false,
            ],
        ]);

        $school = $this->school->fresh('settings');
        $recap = app(\App\Services\DailyReportService::class)->recap($school, '2026-11-25');
        $text = app(\App\Services\DailyReportService::class)->whatsappText($school, '2026-11-25', $recap);

        $this->assertStringContainsString('Terlambat: Siswa Satu', $text);
        $this->assertStringContainsString('Tidak Hadir: Siswa Dua', $text);
        $this->assertStringNotContainsString('Hadir: 0', $text);
        $this->assertStringNotContainsString('Belum Masuk:', $text);
        $this->assertStringNotContainsString('|', $text); // baris rekap jumlah nonaktif
    }

    public function test_whatsapp_text_default_sections_unchanged(): void
    {
        $this->makeSession('2026-11-25', 'terlambat', '2026-11-25 00:30:00');

        $school = $this->school->fresh('settings');
        $recap = app(\App\Services\DailyReportService::class)->recap($school, '2026-11-25');
        $text = app(\App\Services\DailyReportService::class)->whatsappText($school, '2026-11-25', $recap);

        // Bawaan: rekap jumlah + daftar terlambat & tidak_hadir.
        $this->assertStringContainsString('Terlambat: Siswa Satu', $text);
        $this->assertStringContainsString('Hadir: 0 | Terlambat: 1 | Tidak Hadir: 0 | Belum Masuk: 0', $text);
    }

    public function test_admin_can_save_wa_report_options(): void
    {
        $this->actingAs($this->admin)->put(route('settings.update'), [
            'check_in_time' => '07:00',
            'check_out_time' => '15:00',
            'weekly_off_days' => [6, 7],
            'wa_report' => ['counts' => false, 'terlambat' => true, 'tidak_hadir' => true],
        ])->assertRedirect();

        $options = $this->school->settings()->first()->wa_report_options;
        $this->assertFalse($options['counts']);
        $this->assertTrue($options['terlambat']);
        $this->assertArrayNotHasKey('hadir', $options);
    }
}
