<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\ReaderProfile;
use App\Models\RegistrationScanAttempt;
use App\Models\RegistrationSession;
use App\Models\RfidCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCardAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CardManagementTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private AcademicYear $year;
    private User $admin;
    private User $petugas;
    private SchoolClass $class;
    private Student $studentA;
    private Student $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SMA Kartu', 'code' => 'SMA-KARTU', 'timezone' => 'Asia/Jakarta']);
        $this->admin = $this->user('admin@kartu.test', 'admin');
        $this->petugas = $this->user('piket@kartu.test', 'petugas_piket');

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026/2027',
            'start_date' => '2026-07-01', 'end_date' => '2027-06-01', 'status' => 'active',
        ]);
        $grade = Grade::create(['school_id' => $this->school->id, 'name' => 'X', 'level' => 10]);
        $conc = \App\Models\Concentration::create(['school_id' => $this->school->id, 'name' => 'IPA']);
        $this->class = SchoolClass::create([
            'school_id' => $this->school->id,
            'grade_id' => $grade->id, 'concentration_id' => $conc->id, 'code' => 'X-1', 'name' => 'X IPA 1',
        ]);

        $this->studentA = $this->student('2026001', 'Siswa A');
        $this->studentB = $this->student('2026002', 'Siswa B');
    }

    private function user(string $email, string $role): User
    {
        return User::create([
            'name' => $email, 'email' => $email, 'password' => Hash::make('password'),
            'school_id' => $this->school->id, 'role' => $role, 'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function student(string $no, string $name): Student
    {
        $s = Student::create([
            'school_id' => $this->school->id, 'student_no' => $no, 'name' => $name, 'status' => 'active',
        ]);
        $s->memberships()->create([
            'class_id' => $this->class->id,
            'academic_year_id' => $this->year->id,
            'from_date' => '2026-07-01',
            'attendance_number' => (int) substr($no, -1),
        ]);

        return $s;
    }

    private function card(string $uid, string $status = 'unassigned'): RfidCard
    {
        return RfidCard::create(['uid_text' => $uid, 'status' => $status]);
    }

    private function openSession(User $operator): RegistrationSession
    {
        return RegistrationSession::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'operator_user_id' => $operator->id,
            'status' => 'running',
            'snapshot' => [['student_id' => $this->studentA->id], ['student_id' => $this->studentB->id]],
            'started_at' => now(),
        ]);
    }

    public function test_admin_can_register_and_assign_card(): void
    {
        $card = $this->card('UID001');

        $this->actingAs($this->admin)
            ->post(route('master.cards.assign', $card), ['student_id' => $this->studentA->id])
            ->assertRedirect();

        $this->assertDatabaseHas('student_card_assignments', [
            'rfid_card_id' => $card->id,
            'student_id' => $this->studentA->id,
            'status' => 'active',
            'active_flag' => 'ACTIVE',
        ]);
        $this->assertEquals('active', $card->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'card.assigned']);
    }

    public function test_student_cannot_have_two_active_cards(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.cards.assign', $this->card('UID001')), ['student_id' => $this->studentA->id]);

        $this->actingAs($this->admin)
            ->post(route('master.cards.assign', $this->card('UID002')), ['student_id' => $this->studentA->id])
            ->assertSessionHasErrors('student_id');

        $this->assertEquals(1, StudentCardAssignment::where('student_id', $this->studentA->id)->where('active_flag', 'ACTIVE')->count());
    }

    public function test_card_in_use_cannot_be_reassigned(): void
    {
        $card = $this->card('UID001');
        $this->actingAs($this->admin)
            ->post(route('master.cards.assign', $card), ['student_id' => $this->studentA->id]);

        $this->actingAs($this->admin)
            ->post(route('master.cards.assign', $card), ['student_id' => $this->studentB->id])
            ->assertSessionHasErrors('uid_text');
    }

    public function test_blocked_card_cannot_be_assigned(): void
    {
        $card = $this->card('UID001', 'lost');

        $this->actingAs($this->admin)
            ->post(route('master.cards.assign', $card), ['student_id' => $this->studentA->id])
            ->assertSessionHasErrors('uid_text');
    }

    public function test_unassign_keeps_history_and_frees_card(): void
    {
        $card = $this->card('UID001');
        $this->actingAs($this->admin)
            ->post(route('master.cards.assign', $card), ['student_id' => $this->studentA->id]);

        $assignment = StudentCardAssignment::where('rfid_card_id', $card->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('master.assignments.unassign', $assignment), ['reason' => 'Ganti kartu'])
            ->assertRedirect();

        $this->assertEquals('unassigned', $assignment->fresh()->status);
        $this->assertEquals('unassigned', $card->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'card.unassigned']);

        // Kartu bisa ditautkan ulang ke siswa lain.
        $this->actingAs($this->admin)
            ->post(route('master.cards.assign', $card->fresh()), ['student_id' => $this->studentB->id])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_non_operator_cannot_assign_card(): void
    {
        $wali = $this->user('wali@kartu.test', 'wali_kelas');
        $card = $this->card('UID001');

        $this->actingAs($wali)
            ->post(route('master.cards.assign', $card), ['student_id' => $this->studentA->id])
            ->assertForbidden();
    }

    public function test_bulk_scan_creates_and_assigns_new_card(): void
    {
        $session = $this->openSession($this->petugas);

        $res = $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'NEWUID123',
            ]);

        $res->assertOk()->assertJson(['outcome' => 'assigned_new_card']);
        $this->assertDatabaseHas('rfid_cards', ['uid_text' => 'NEWUID123', 'status' => 'active']);
        $this->assertDatabaseHas('registration_scan_attempts', [
            'registration_session_id' => $session->id,
            'student_id' => $this->studentA->id,
            'outcome' => 'assigned_new_card',
        ]);
    }

    public function test_bulk_scan_existing_card_and_duplicate_detection(): void
    {
        $card = $this->card('UID001', 'unassigned');
        $session = $this->openSession($this->petugas);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'UID001',
            ])->assertJson(['outcome' => 'assigned']);

        // Scan ulang kartu yang sama untuk siswa yang sama → already_assigned.
        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'UID001',
            ])->assertJson(['outcome' => 'already_assigned']);

        // Kartu yang sama untuk siswa lain → card_in_use.
        // Usia semua attempt terakhir agar tidak dianggap duplicate_read (bukan reread).
        RegistrationScanAttempt::query()->update(['attempted_at' => now()->subMinutes(2)]);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentB->id, 'uid_text' => 'UID001',
            ])->assertJson(['outcome' => 'card_in_use']);
    }

    public function test_bulk_scan_student_with_existing_card(): void
    {
        // Siswa A sudah punya kartu aktif.
        $this->actingAs($this->admin)
            ->post(route('master.cards.assign', $this->card('UIDOLD')), ['student_id' => $this->studentA->id]);

        $session = $this->openSession($this->petugas);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'UIDNEW',
            ])->assertJson(['outcome' => 'student_has_card']);
    }

    public function test_bulk_scan_validates_uid_against_reader_profile(): void
    {
        $profile = ReaderProfile::create([
            'name' => 'EM-Kiosk', 'pattern' => '^[0-9A-F]{8}$', 'is_active' => true,
        ]);
        $session = RegistrationSession::create([
            'school_id' => $this->school->id, 'class_id' => $this->class->id,
            'operator_user_id' => $this->petugas->id, 'reader_profile_id' => $profile->id,
            'status' => 'running', 'snapshot' => [['student_id' => $this->studentA->id]], 'started_at' => now(),
        ]);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'bukan-hex!!',
            ])->assertJson(['outcome' => 'invalid_uid']);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'A1B2C3D4',
            ])->assertJson(['outcome' => 'assigned_new_card']);
    }

    public function test_finished_session_rejects_scans(): void
    {
        $session = $this->openSession($this->petugas);
        $session->update(['status' => 'finished', 'finished_at' => now()]);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'UID001',
            ])
            ->assertStatus(422);
    }

    public function test_student_outside_class_cannot_be_scanned(): void
    {
        $outsider = Student::create([
            'school_id' => $this->school->id, 'student_no' => '2026999', 'name' => 'Siswa Luar', 'status' => 'active',
        ]);
        $session = $this->openSession($this->petugas);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $outsider->id, 'uid_text' => 'UID001',
            ])
            ->assertStatus(422);
    }

    public function test_pause_blocks_scan_and_resume_allows_it(): void
    {
        $session = $this->openSession($this->petugas);

        $this->actingAs($this->petugas)
            ->post(route('master.registrations.pause', $session))->assertRedirect();
        $this->assertEquals('paused', $session->fresh()->status);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'UIDPAUSE',
            ])->assertStatus(422);

        $this->actingAs($this->petugas)
            ->post(route('master.registrations.resume', $session))->assertRedirect();
        $this->assertEquals('running', $session->fresh()->status);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'UIDPAUSE',
            ])->assertOk()->assertJson(['outcome' => 'assigned_new_card']);
    }

    public function test_scan_returns_next_pending_student(): void
    {
        $session = $this->openSession($this->petugas);

        $res = $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'UIDNEXT',
            ]);

        $res->assertOk()->assertJson(['next_student_id' => $this->studentB->id]);

        // Setelah semua punya kartu → next null.
        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentB->id, 'uid_text' => 'UIDNEXT2',
            ])->assertJson(['next_student_id' => null]);
    }

    public function test_skip_excludes_student_from_queue_without_deleting_history(): void
    {
        $session = $this->openSession($this->petugas);

        $this->actingAs($this->petugas)
            ->post(route('master.registrations.skip', $session), ['student_id' => $this->studentA->id])
            ->assertRedirect();

        $this->assertDatabaseHas('registration_scan_attempts', [
            'registration_session_id' => $session->id,
            'student_id' => $this->studentA->id,
            'outcome' => 'skipped',
        ]);

        // Skip B juga → antrean kosong.
        $this->actingAs($this->petugas)
            ->post(route('master.registrations.skip', $session), ['student_id' => $this->studentB->id]);

        $this->actingAs($this->petugas)
            ->get(route('master.registrations.show', $session))
            ->assertInertia(fn ($page) => $page
                ->where('pending', 0)
                ->where('nextStudentId', null));
    }

    public function test_cancel_does_not_remove_successful_assignments(): void
    {
        $session = $this->openSession($this->petugas);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'UIDCANCEL',
            ])->assertOk();

        $this->actingAs($this->petugas)
            ->post(route('master.registrations.cancel', $session), [
                'reason' => 'Berhenti di tengah',
            ])->assertRedirect();

        $this->assertEquals('cancelled', $session->fresh()->status);
        // Assignment sukses tetap ada — tidak di-rollback diam-diam.
        $this->assertDatabaseHas('student_card_assignments', [
            'student_id' => $this->studentA->id, 'status' => 'active', 'active_flag' => 'ACTIVE',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.cancelled']);
    }

    public function test_cancelled_session_rejects_scan_and_skip(): void
    {
        $session = $this->openSession($this->petugas);
        $session->update(['status' => 'cancelled', 'finished_at' => now()]);

        $this->actingAs($this->petugas)
            ->postJson(route('master.registrations.scan', $session), [
                'student_id' => $this->studentA->id, 'uid_text' => 'UIDX',
            ])->assertStatus(422);

        $this->actingAs($this->petugas)
            ->post(route('master.registrations.skip', $session), ['student_id' => $this->studentA->id])
            ->assertStatus(422);
    }
}
