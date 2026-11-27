<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\Grade;
use App\Models\RawRfidEvent;
use App\Models\RfidCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolHoliday;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentCardAssignment;
use App\Models\Terminal;
use App\Models\TerminalToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private Terminal $inTerminal;
    private Terminal $outTerminal;
    private string $token;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SMA API', 'code' => 'SMA-API', 'timezone' => 'Asia/Jakarta']);

        SchoolSetting::create([
            'school_id' => $this->school->id,
            'check_in_time' => '07:00:00',
            'check_out_time' => '15:00:00',
        ]);

        $year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active',
        ]);
        $grade = Grade::create(['school_id' => $this->school->id, 'name' => 'X', 'level' => 10]);
        $conc = \App\Models\Concentration::create(['school_id' => $this->school->id, 'name' => 'IPA']);
        $class = SchoolClass::create([
            'school_id' => $this->school->id,
            'grade_id' => $grade->id, 'concentration_id' => $conc->id, 'code' => 'X-1', 'name' => 'X IPA 1',
            'status' => 'active',
        ]);

        $this->student = Student::create([
            'school_id' => $this->school->id, 'name' => 'Siswa API', 'status' => 'active',
        ]);
        $this->student->memberships()->create([
            'class_id' => $class->id, 'academic_year_id' => $year->id, 'from_date' => '2026-01-01',
        ]);

        $card = RfidCard::create(['uid_text' => 'UID123', 'status' => 'active']);
        StudentCardAssignment::create([
            'rfid_card_id' => $card->id, 'student_id' => $this->student->id,
            'assigned_at' => now(), 'status' => 'active', 'active_flag' => 'ACTIVE',
        ]);

        $this->inTerminal = Terminal::create([
            'school_id' => $this->school->id, 'terminal_code' => 'IN-01', 'type' => 'in', 'status' => 'active',
        ]);
        $this->outTerminal = Terminal::create([
            'school_id' => $this->school->id, 'terminal_code' => 'OUT-01', 'type' => 'out', 'status' => 'active',
        ]);

        $this->token = $this->tokenFor($this->inTerminal);
    }

    private function tokenFor(Terminal $terminal): string
    {
        $plain = 'td_'.Str::random(48);
        TerminalToken::create(['terminal_id' => $terminal->id, 'token_hash' => hash('sha256', $plain)]);

        return $plain;
    }

    private function postEvents(array $events, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token ?? $this->token)
            ->postJson('/api/v1/device/events', ['events' => $events]);
    }

    private function event(string $eventId, string $uid = 'UID123', string $time = '2026-11-25 00:30:00'): array
    {
        // 00:30 UTC = 07:30 WIB → lewat jam masuk 07:00 → terlambat.
        return ['event_id' => $eventId, 'uid_text' => $uid, 'event_time' => $time];
    }

    public function test_requires_valid_terminal_token(): void
    {
        $this->postJson('/api/v1/device/events', ['events' => [$this->event('e1')]])->assertUnauthorized();
        $this->withToken('td_salah')->postJson('/api/v1/device/events', ['events' => [$this->event('e1')]])->assertUnauthorized();
    }

    public function test_entry_before_check_in_time_is_hadir(): void
    {
        // 23:30 UTC = 06:30 WIB < 07:00.
        $res = $this->postEvents([$this->event('evt-1', 'UID123', '2026-11-24 23:30:00')]);

        $res->assertOk()->assertJsonPath('results.0.status', 'accepted')
            ->assertJsonPath('results.0.outcome', 'hadir');

        $session = AttendanceSession::where('student_id', $this->student->id)->firstOrFail();
        $this->assertEquals('hadir', $session->attendance_status);
        $this->assertNotNull($session->check_in_at);
        $this->assertEquals('2026-11-25', $session->school_date->toDateString());
        $this->assertEquals('06:30', $session->check_in_at->setTimezone('Asia/Jakarta')->format('H:i'));
        $this->assertDatabaseHas('raw_rfid_events', ['event_id' => 'evt-1', 'outcome' => 'hadir']);
    }

    public function test_entry_after_check_in_time_is_terlambat(): void
    {
        $this->postEvents([$this->event('evt-late')])
            ->assertJsonPath('results.0.outcome', 'terlambat');

        $this->assertEquals('terlambat', AttendanceSession::firstOrFail()->attendance_status);
    }

    public function test_exit_records_check_out(): void
    {
        $this->postEvents([$this->event('in-1', 'UID123', '2026-11-24 23:30:00')]);

        $outToken = $this->tokenFor($this->outTerminal);
        $this->withToken($outToken)->postJson('/api/v1/device/events', [
            'events' => [$this->event('out-1', 'UID123', '2026-11-25 08:00:00')], // 15:00 WIB
        ])->assertJsonPath('results.0.outcome', 'pulang');

        $session = AttendanceSession::firstOrFail();
        $this->assertNotNull($session->check_out_at);
        $this->assertEquals('15:00', $session->check_out_at->setTimezone('Asia/Jakarta')->format('H:i'));
    }

    public function test_exit_without_check_in_does_not_change_status(): void
    {
        $outToken = $this->tokenFor($this->outTerminal);

        $this->withToken($outToken)->postJson('/api/v1/device/events', [
            'events' => [$this->event('out-x', 'UID123', '2026-11-25 03:00:00')],
        ])->assertJsonPath('results.0.outcome', 'tanpa_check_in');

        // Sesi dibuat oleh tap OUT tapi status tetap belum_masuk, check_out kosong.
        $session = AttendanceSession::firstOrFail();
        $this->assertEquals('belum_masuk', $session->attendance_status);
        $this->assertNull($session->check_out_at);
    }

    public function test_repeat_entry_is_ignored(): void
    {
        $this->postEvents([$this->event('in-1', 'UID123', '2026-11-24 23:30:00')]);
        $this->postEvents([$this->event('in-2', 'UID123', '2026-11-25 00:35:00')], null)
            ->assertJsonPath('results.0.outcome', 'sudah_di_sekolah');

        $this->assertNotNull(AttendanceSession::firstOrFail()->check_in_at);
    }

    public function test_holiday_produces_no_session(): void
    {
        SchoolHoliday::create([
            'school_id' => $this->school->id,
            'holiday_date' => '2026-11-25',
            'name' => 'Libur Uji',
        ]);

        $this->postEvents([$this->event('evt-holiday')])
            ->assertJsonPath('results.0.outcome', 'libur');

        $this->assertEquals(0, AttendanceSession::count());
        $this->assertDatabaseHas('raw_rfid_events', ['event_id' => 'evt-holiday', 'outcome' => 'libur']);
    }

    public function test_weekend_is_not_school_day(): void
    {
        // 2026-11-28 adalah Sabtu.
        $this->postEvents([$this->event('evt-sat', 'UID123', '2026-11-28 00:30:00')])
            ->assertJsonPath('results.0.outcome', 'libur');

        $this->assertEquals(0, AttendanceSession::count());
    }

    public function test_unknown_and_inactive_cards(): void
    {
        $this->postEvents([$this->event('evt-x', 'UNKNOWN')])
            ->assertJsonPath('results.0.outcome', 'unknown_card');

        RfidCard::create(['uid_text' => 'UIDBAD', 'status' => 'lost'])->assignments()->create([
            'student_id' => $this->student->id, 'assigned_at' => now(),
            'status' => 'unassigned', 'active_flag' => null,
        ]);
        // Kartu non-assignable langsung → inactive_card tanpa assignment aktif.
        $this->postEvents([$this->event('evt-bad', 'UIDBAD')])
            ->assertJsonPath('results.0.outcome', 'inactive_card');

        $this->assertEquals(0, AttendanceSession::count());
    }

    public function test_ingest_is_idempotent_by_event_id(): void
    {
        $this->postEvents([$this->event('evt-dup', 'UID123', '2026-11-24 23:30:00')])->assertOk();
        $count = RawRfidEvent::count();

        $res = $this->postEvents([$this->event('evt-dup', 'UID123', '2026-11-24 23:30:00')]);
        $res->assertOk()->assertJsonPath('results.0.status', 'duplicate');

        $this->assertEquals($count, RawRfidEvent::count());
    }

    public function test_duplicate_tap_within_debounce_is_ignored(): void
    {
        $this->postEvents([$this->event('in-1', 'UID123', '2026-11-24 23:30:00')]);
        // Tap sama 2 detik kemudian di terminal yang sama → debounce.
        $this->postEvents([$this->event('in-2', 'UID123', '2026-11-24 23:30:02')])
            ->assertJsonPath('results.0.outcome', 'ignored_duplicate_scan');
    }

    public function test_revoked_token_is_rejected(): void
    {
        TerminalToken::where('terminal_id', $this->inTerminal->id)->update(['revoked_at' => now()]);

        $this->postEvents([$this->event('e1')])->assertUnauthorized();
    }

    public function test_pairing_code_exchanges_for_token_once(): void
    {
        $code = 'ABCD-2345';
        \App\Models\DevicePairingCode::create([
            'terminal_id' => $this->inTerminal->id,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(15),
        ]);

        $res = $this->postJson('/api/v1/device/pair', ['pairing_code' => strtolower($code)])
            ->assertOk()
            ->assertJsonPath('terminal.code', 'IN-01');

        $plain = $res->json('token');
        $this->assertStringStartsWith('td_', $plain);

        $this->withToken($plain)->postJson('/api/v1/device/events', [
            'events' => [$this->event('pair-1')],
        ])->assertOk()->assertJsonPath('results.0.status', 'accepted');

        $this->postJson('/api/v1/device/pair', ['pairing_code' => $code])->assertStatus(422);
    }

    public function test_config_and_heartbeat(): void
    {
        $profile = \App\Models\ReaderProfile::create([
            'name' => 'EM-Kiosk', 'pattern' => '^[0-9A-F]{8}$', 'uid_length' => 8, 'is_active' => true,
        ]);
        $this->inTerminal->update(['reader_profile_id' => $profile->id]);

        $this->withToken($this->token)->getJson('/api/v1/device/config')
            ->assertOk()
            ->assertJsonPath('reader.name', 'EM-Kiosk')
            ->assertJsonPath('timezone', 'Asia/Jakarta');

        $this->withToken($this->token)->postJson('/api/v1/device/heartbeat', [
            'app_version' => '1.2.0',
            'queue_pending' => 3,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertEquals('1.2.0', $this->inTerminal->fresh()->app_version);
    }
}
