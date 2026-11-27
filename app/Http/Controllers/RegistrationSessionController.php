<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationSessionRequest;
use App\Http\Requests\ScanAttemptRequest;
use App\Models\ReaderProfile;
use App\Models\RegistrationScanAttempt;
use App\Models\RegistrationSession;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCardAssignment;
use App\Services\AuditLogger;
use App\Services\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationSessionController extends Controller
{
    private const REGISTERED = ['assigned', 'assigned_new_card'];

    public function __construct(private CardService $cards) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RegistrationSession::class);

        $sessions = RegistrationSession::where('school_id', $request->user()->school_id)
            ->with(['class:id,name,code', 'operator:id,name'])
            ->withCount('attempts')
            ->latest()
            ->paginate(20);

        return Inertia::render('Master/Registrations/Index', ['sessions' => $sessions]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', RegistrationSession::class);

        return Inertia::render('Master/Registrations/Create', [
            'classes' => $this->classes($request),
            'readerProfiles' => ReaderProfile::where('is_active', true)->get(['id', 'name', 'pattern']),
        ]);
    }

    public function store(RegistrationSessionRequest $request): RedirectResponse
    {
        $this->authorize('create', RegistrationSession::class);

        // Snapshot roster saat sesi dibuka — histori tidak berubah walau
        // keanggotaan kelas diedit belakangan.
        $roster = $this->roster($request->validated('class_id'));

        $session = RegistrationSession::create([
            'school_id' => $request->user()->school_id,
            'class_id' => $request->validated('class_id'),
            'operator_user_id' => $request->user()->id,
            'reader_profile_id' => $request->validated('reader_profile_id'),
            'status' => 'running',
            'snapshot' => $roster,
            'started_at' => now(),
        ]);

        AuditLogger::log('registration.started', RegistrationSession::class, $session,
            after: ['class_id' => $session->class_id, 'roster_size' => count($roster)]);

        return redirect()->route('master.registrations.show', $session)
            ->with('success', 'Sesi registrasi dimulai.');
    }

    public function show(Request $request, RegistrationSession $session): Response
    {
        $this->authorize('view', $session);

        $session->load(['class.grade:id,name', 'operator:id,name', 'readerProfile:id,name']);

        $roster = $this->roster($session->class_id);
        $students = Student::whereIn('id', collect($roster)->pluck('student_id'))
            ->with(['activeCardAssignment.card:id,uid_text,status'])
            ->get()
            ->keyBy('id');

        $rows = collect($roster)->map(fn ($r) => $r + [
            'card_uid' => $students->get($r['student_id'])?->activeCardAssignment?->card?->uid_text,
            'card_status' => $students->get($r['student_id'])?->activeCardAssignment?->card?->status,
        ])->values();

        $attempts = $session->attempts()->with('student:id,name')->latest('attempted_at')->limit(50)->get();
        $skippedIds = $this->skippedIds($session);
        $cardedIds = $rows->whereNotNull('card_uid')->pluck('student_id')->all();
        $pending = collect($rows)->whereNotIn('student_id', array_merge($cardedIds, $skippedIds))->values();

        // Ringkasan outcome seluruh scan (bukan hanya 50 terakhir).
        $outcomeSummary = $session->attempts()
            ->selectRaw('outcome, count(*) as total')
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        return Inertia::render('Master/Registrations/Show', [
            'session' => $session,
            'rows' => $rows->map(fn ($r) => $r + ['skipped' => in_array($r['student_id'], $skippedIds, true)])->values(),
            'attempts' => $attempts,
            'registered' => count($cardedIds),
            'total' => $rows->count(),
            'pending' => $pending->count(),
            'nextStudentId' => $pending->first()['student_id'] ?? null,
            'outcomeSummary' => $outcomeSummary,
        ]);
    }

    /** Siswa yang di-skip petugas (tercatat append-only di attempts). */
    private function skippedIds(RegistrationSession $session): array
    {
        return $session->attempts()
            ->where('outcome', 'skipped')
            ->whereNotNull('student_id')
            ->pluck('student_id')->all();
    }

    /** Siswa berikutnya yang belum punya kartu & belum di-skip (urutan roster). */
    private function nextPending(RegistrationSession $session): ?int
    {
        $rosterIds = collect($session->snapshot ?? [])->pluck('student_id');
        $cardedIds = Student::whereIn('id', $rosterIds)
            ->whereHas('activeCardAssignment')
            ->pluck('id')->all();
        $done = array_merge($cardedIds, $this->skippedIds($session));

        return $rosterIds->first(fn ($id) => ! in_array($id, $done, true));
    }

    /**
     * Endpoint scan keyboard-wedge: UID dikirim sebagai teks, outcome
     * selalu dicatat ke registration_scan_attempts (append-only).
     */
    public function scan(ScanAttemptRequest $request, RegistrationSession $session): JsonResponse
    {
        $this->authorize('update', $session);
        abort_unless($session->status === 'running', 422, 'Sesi tidak berjalan.');

        $student = Student::where('school_id', $request->user()->school_id)
            ->whereKey($request->validated('student_id'))
            ->firstOrFail();

        // Siswa harus anggota kelas sesi (snapshot).
        $inRoster = collect($session->snapshot ?? [])->contains('student_id', $student->id);
        abort_unless($inRoster, 422, 'Siswa bukan anggota kelas sesi ini.');

        $profile = $session->reader_profile_id ? ReaderProfile::find($session->reader_profile_id) : null;
        [$outcome, $card] = $this->cards->processScan($request->validated('uid_text'), $student, $profile);

        if ($outcome === 'card_in_use' && $this->isRereadOfLastRegistration($session, $card)) {
            $outcome = 'duplicate_read';
        }

        RegistrationScanAttempt::create([
            'registration_session_id' => $session->id,
            'uid_text' => trim($request->validated('uid_text')),
            'student_id' => $student->id,
            'outcome' => $outcome,
            'detail' => ['rfid_card_id' => $card?->id],
            'attempted_at' => now(),
        ]);

        return response()->json([
            'outcome' => $outcome,
            'uid' => $card?->uid_text ?? trim($request->validated('uid_text')),
            'student' => $student->name,
            'next_student_id' => $this->nextPending($session),
        ]);
    }

    /**
     * Kartu yang baru didaftarkan dan terbaca lagi (belum diangkat dari reader)
     * bukan kartu yang dipakai siswa lain. Reader tidak mengirim sinyal "diangkat".
     */
    private function isRereadOfLastRegistration(RegistrationSession $session, ?RfidCard $card): bool
    {
        $last = $session->attempts()
            ->whereIn('outcome', self::REGISTERED)
            ->where('attempted_at', '>=', now()->subMinute())
            ->latest('id')->first();

        return $card !== null && ($last?->detail['rfid_card_id'] ?? null) === $card->id;
    }

    /** Pause: scan ditolak selama paused; assignment yang sudah sukses tetap. */
    public function pause(Request $request, RegistrationSession $session): RedirectResponse
    {
        return $this->transition($request, $session, 'paused', ['running']);
    }

    public function resume(Request $request, RegistrationSession $session): RedirectResponse
    {
        return $this->transition($request, $session, 'running', ['paused']);
    }

    /**
     * Skip siswa saat ini — dicatat sebagai attempt 'skipped' (append-only),
     * bukan menghapus apa pun.
     */
    public function skip(Request $request, RegistrationSession $session): RedirectResponse
    {
        $this->authorize('update', $session);
        abort_unless($session->status === 'running', 422, 'Sesi tidak berjalan.');

        $data = $request->validate(['student_id' => ['required', 'integer']]);
        $student = Student::where('school_id', $request->user()->school_id)->findOrFail($data['student_id']);
        abort_unless(collect($session->snapshot ?? [])->contains('student_id', $student->id), 422);

        RegistrationScanAttempt::create([
            'registration_session_id' => $session->id,
            'student_id' => $student->id,
            'outcome' => 'skipped',
            'attempted_at' => now(),
        ]);

        return back()->with('success', "{$student->name} di-skip.");
    }

    /**
     * Batalkan pendaftaran terakhir yang masih aktif (salah pasangan). Riwayat tidak
     * dihapus: assignment dilepas dan pembatalan dicatat sebagai attempt 'undone'.
     */
    public function undoLast(Request $request, RegistrationSession $session): RedirectResponse
    {
        $this->authorize('update', $session);
        abort_unless(in_array($session->status, ['running', 'paused'], true), 422, 'Sesi sudah ditutup.');

        $undone = $session->attempts()->where('outcome', 'undone')->get()
            ->map(fn ($a) => $a->detail['undoes'] ?? null)->filter()->all();

        $last = $session->attempts()->whereIn('outcome', self::REGISTERED)
            ->whereNotIn('id', $undone)->latest('id')->first();
        abort_unless($last, 422, 'Tidak ada pendaftaran yang bisa dibatalkan.');

        $assignment = StudentCardAssignment::where('student_id', $last->student_id)
            ->where('rfid_card_id', $last->detail['rfid_card_id'] ?? 0)
            ->where('active_flag', CardService::ACTIVE_FLAG)
            ->first();
        abort_unless($assignment, 422, 'Pendaftaran terakhir sudah tidak aktif.');

        DB::transaction(function () use ($request, $session, $last, $assignment) {
            $this->cards->unassign($assignment, $request->user(), "Batal pendaftaran terakhir (sesi #{$session->id})");

            RegistrationScanAttempt::create([
                'registration_session_id' => $session->id,
                'uid_text' => $last->uid_text,
                'student_id' => $last->student_id,
                'outcome' => 'undone',
                'detail' => ['undoes' => $last->id, 'rfid_card_id' => $last->detail['rfid_card_id'] ?? null],
                'attempted_at' => now(),
            ]);
        });

        return back()->with('success', 'Pendaftaran terakhir dibatalkan.');
    }

    /**
     * Cancel sesi — assignment yang sudah sukses TIDAK di-rollback
     * (sesuai aturan: pembatalan eksplisit & tercatat, bukan diam-diam).
     */
    public function cancel(Request $request, RegistrationSession $session): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->transition($request, $session, 'cancelled', ['running', 'paused'], $data['reason'] ?? null);
    }

    private function transition(Request $request, RegistrationSession $session, string $to, array $from, ?string $reason = null): RedirectResponse
    {
        $this->authorize('update', $session);
        abort_unless(in_array($session->status, $from, true), 422, "Sesi tidak dalam status yang bisa diubah ke {$to}.");

        $session->update([
            'status' => $to,
            'finished_at' => $to === 'running' ? $session->finished_at : now(),
        ]);
        AuditLogger::log('registration.'.$to, RegistrationSession::class, $session, reason: $reason);

        return back()->with('success', 'Sesi registrasi '.$to.'.');
    }

    public function finish(Request $request, RegistrationSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        if (in_array($session->status, ['running', 'paused'], true)) {
            $session->update(['status' => 'finished', 'finished_at' => now()]);
            AuditLogger::log('registration.finished', RegistrationSession::class, $session);
        }

        return redirect()->route('master.registrations.show', $session)->with('success', 'Sesi registrasi ditutup.');
    }

    /** Daftar siswa aktif di kelas (keanggotaan efektif, to_date null). */
    private function roster(int $classId): array
    {
        return DB::table('student_class_memberships as m')
            ->join('students as s', 's.id', '=', 'm.student_id')
            ->where('m.class_id', $classId)
            ->whereNull('m.to_date')
            ->where('s.status', 'active')
            ->orderBy('m.attendance_number')
            ->orderBy('s.name')
            ->get(['s.id as student_id', 's.student_no', 's.name', 'm.attendance_number'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    private function classes(Request $request)
    {
        return SchoolClass::where('school_id', $request->user()->school_id)
            ->where('status', 'active')
            ->with('grade:id,name')
            ->orderBy('code')
            ->get(['id', 'name', 'code']);
    }
}
