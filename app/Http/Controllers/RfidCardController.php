<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignCardRequest;
use App\Http\Requests\CardRequest;
use App\Http\Requests\UnassignCardRequest;
use App\Models\RfidCard;
use App\Models\StudentCardAssignment;
use App\Services\AuditLogger;
use App\Services\CardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RfidCardController extends Controller
{
    public function __construct(private CardService $cards) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RfidCard::class);

        $query = RfidCard::with(['activeAssignment.student:id,name'])
            ->withCount('assignments');

        // Kartu bersifat global; batasi ke kartu yang belum pernah dipakai
        // atau tertaut ke siswa sekolah ini.
        $schoolId = $request->user()->school_id;
        $query->where(function ($q) use ($schoolId) {
            $q->whereDoesntHave('assignments')
                ->orWhereHas('assignments.student', fn ($s) => $s->where('school_id', $schoolId));
        });

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where('uid_text', 'like', $term);
        }

        $cards = $query->orderBy('uid_text')->paginate(25)->withQueryString();

        return Inertia::render('Master/Cards/Index', [
            'cards' => $cards,
            'filters' => $request->only(['status', 'q']),
            'students' => $request->user()->can('create', RfidCard::class)
                ? \App\Models\Student::where('school_id', $schoolId)->where('status', 'active')->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(CardRequest $request): RedirectResponse
    {
        $this->authorize('create', RfidCard::class);

        $data = $request->safe()->except('status');
        $card = RfidCard::create($data + [
            'status' => 'unassigned',
            'issued_at' => $data['issued_at'] ?? now()->toDateString(),
        ]);

        AuditLogger::log('card.registered', RfidCard::class, $card, after: $card->only(['uid_text', 'card_type', 'status']));

        return redirect()->route('master.cards.index')->with('success', 'Kartu didaftarkan.');
    }

    public function update(CardRequest $request, RfidCard $card): RedirectResponse
    {
        $this->authorize('update', $card);
        $this->guardCardTenant($request, $card);

        $before = $card->only(['uid_text', 'card_type', 'status', 'issued_at']);
        $card->update($request->safe()->except('uid_text'));

        AuditLogger::log('card.updated', RfidCard::class, $card, before: $before, after: $card->only(['uid_text', 'card_type', 'status', 'issued_at']));

        return redirect()->route('master.cards.index')->with('success', 'Kartu diperbarui.');
    }

    public function assign(AssignCardRequest $request, RfidCard $card): RedirectResponse
    {
        $this->authorize('update', $card);
        $this->guardCardTenant($request, $card);

        try {
            $this->cards->assign($card, \App\Models\Student::findOrFail($request->validated('student_id')), $request->user(), $request->validated('reason'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Kartu ditautkan ke siswa.');
    }

    public function unassign(UnassignCardRequest $request, StudentCardAssignment $assignment): RedirectResponse
    {
        $this->authorize('update', $assignment->card);

        // Assignment merujuk siswa — kartu global, tapi siswa punya sekolah.
        abort_unless(
            $assignment->student?->school_id === $request->user()->school_id,
            403
        );

        try {
            $this->cards->unassign($assignment, $request->user(), $request->validated('reason'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Kartu dilepas dari siswa.');
    }

    public function destroy(Request $request, RfidCard $card): RedirectResponse
    {
        $this->authorize('delete', $card);
        $this->guardCardTenant($request, $card);

        DB::transaction(function () use ($card) {
            foreach ($card->assignments()->where('status', 'active')->get() as $assignment) {
                $this->cards->unassign($assignment, null, 'Kartu dihapus dari inventaris.');
            }
            AuditLogger::log('card.deleted', RfidCard::class, $card, before: $card->only(['uid_text', 'status']));
            $card->delete();
        });

        return redirect()->route('master.cards.index')->with('success', 'Kartu dihapus.');
    }

    /**
     * Kartu adalah inventaris global tanpa school_id — tapi kartu yang sedang
     * tertaut ke siswa sekolah lain tidak boleh diubah/dihapus oleh admin
     * sekolah ini (mencegah interferensi lintas tenant).
     */
    private function guardCardTenant(Request $request, RfidCard $card): void
    {
        $studentSchoolId = $card->activeAssignment?->student?->school_id;

        abort_if(
            $studentSchoolId !== null && $studentSchoolId !== $request->user()->school_id,
            403,
            'Kartu sedang dipakai siswa sekolah lain.'
        );
    }
}
