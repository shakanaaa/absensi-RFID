<?php

namespace App\Services;

use App\Models\ReaderProfile;
use App\Models\RfidCard;
use App\Models\Student;
use App\Models\StudentCardAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CardService
{
    public const ACTIVE_FLAG = 'ACTIVE';

    /** Status kartu yang boleh dipakai untuk penugasan baru & resolusi absen. */
    public const ASSIGNABLE = ['active', 'unassigned'];

    /**
     * Tautkan kartu ke siswa. Satu kartu & satu siswa hanya boleh punya
     * satu assignment aktif (dijamin unique index active_flag).
     */
    public function assign(RfidCard $card, Student $student, ?User $actor = null, ?string $reason = null): StudentCardAssignment
    {
        return DB::transaction(function () use ($card, $student, $actor, $reason) {
            // Kartu harus usable dan belum tertaut ke siswa lain.
            if (! in_array($card->status, self::ASSIGNABLE, true)) {
                throw ValidationException::withMessages(['uid_text' => 'Kartu berstatus "'.$card->status.'" tidak dapat ditautkan.']);
            }

            $card->loadMissing('activeAssignment');
            if ($card->activeAssignment) {
                throw ValidationException::withMessages(['uid_text' => 'Kartu sudah tertaut ke siswa lain. Lepas dulu assignment aktifnya.']);
            }

            // Satu siswa hanya boleh punya satu kartu aktif.
            $existing = StudentCardAssignment::where('student_id', $student->id)
                ->where('active_flag', self::ACTIVE_FLAG)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages(['student_id' => 'Siswa sudah memiliki kartu aktif. Lepas kartu lama dulu.']);
            }

            // rfid_cards tidak punya school_id — isolasi tenant lewat siswa.

            $assignment = StudentCardAssignment::create([
                'rfid_card_id' => $card->id,
                'student_id' => $student->id,
                'assigned_by' => $actor?->id,
                'assigned_at' => now(),
                'status' => 'active',
                'active_flag' => self::ACTIVE_FLAG,
                'reason' => $reason,
            ]);

            if ($card->status !== 'active') {
                $card->update(['status' => 'active']);
            }

            AuditLogger::log('card.assigned', StudentCardAssignment::class, $assignment,
                after: ['card_uid' => $card->uid_text, 'student_id' => $student->id],
                reason: $reason, actor: $actor);

            return $assignment;
        });
    }

    /** Lepas kartu dari siswa; histori assignment tetap tersimpan. */
    public function unassign(StudentCardAssignment $assignment, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($assignment, $actor, $reason) {
            $assignment->loadMissing(['card', 'student']);

            if ($assignment->status !== 'active' || $assignment->active_flag !== self::ACTIVE_FLAG) {
                throw ValidationException::withMessages(['assignment' => 'Assignment sudah tidak aktif.']);
            }

            $assignment->update([
                'status' => 'unassigned',
                'active_flag' => null,
                'unassigned_at' => now(),
                'reason' => $reason ?? $assignment->reason,
            ]);

            $card = $assignment->card;
            if ($card && in_array($card->status, ['active'], true)) {
                $card->update(['status' => 'unassigned']);
            }

            AuditLogger::log('card.unassigned', StudentCardAssignment::class, $assignment,
                before: ['card_uid' => $card?->uid_text, 'student_id' => $assignment->student_id],
                reason: $reason, actor: $actor);
        });
    }

    /**
     * Proses satu UID hasil scan pada sesi registrasi.
     * Mengembalikan [outcome, RfidCard|null, StudentCardAssignment|null].
     * Tidak pernah throw untuk kasus bisnis — outcome yang menyatakan hasil.
     */
    public function processScan(string $uidText, Student $student, ?ReaderProfile $profile = null): array
    {
        $uid = trim($uidText);

        if ($uid === '' || mb_strlen($uid) > 64) {
            return ['invalid_uid', null, null];
        }

        if ($profile?->pattern && ! preg_match('/'.$profile->pattern.'/', $uid)) {
            return ['invalid_uid', null, null];
        }

        $card = RfidCard::where('uid_text', $uid)->first();

        if (! $card) {
            $card = RfidCard::create([
                'uid_text' => $uid,
                'card_type' => 'em125k',
                'status' => 'unassigned',
                'issued_at' => now()->toDateString(),
            ]);

            try {
                $assignment = $this->assign($card, $student);

                return ['assigned_new_card', $card, $assignment];
            } catch (ValidationException $e) {
                return ['student_has_card', $card, null];
            }
        }

        if (! in_array($card->status, self::ASSIGNABLE, true)) {
            return ['card_blocked', $card, null];
        }

        $card->loadMissing('activeAssignment.student');
        $active = $card->activeAssignment;

        if ($active) {
            return $active->student_id === $student->id
                ? ['already_assigned', $card, $active]
                : ['card_in_use', $card, $active];
        }

        try {
            $assignment = $this->assign($card, $student);

            return ['assigned', $card, $assignment];
        } catch (ValidationException $e) {
            return ['student_has_card', $card, null];
        }
    }
}
