<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawRfidEvent;
use App\Services\AttendanceEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceEventController extends Controller
{
    public function __construct(private AttendanceEvaluator $evaluator) {}

    /** Info terminal untuk handshake awal klien edge. */
    public function me(Request $request): JsonResponse
    {
        $terminal = $request->attributes->get('terminal');

        return response()->json([
            'terminal' => [
                'id' => $terminal->id,
                'code' => $terminal->terminal_code,
                'type' => $terminal->type,
                'location' => $terminal->location,
                'school_id' => $terminal->school_id,
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Ingest batch event RFID dari terminal.
     * Idempotent: event_id yang sudah ada dikembalikan sebagai 'duplicate'
     * tanpa evaluasi ulang (PRD FR-EVT-002).
     */
    public function store(Request $request): JsonResponse
    {
        $terminal = $request->attributes->get('terminal');

        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:500'],
            'events.*.event_id' => ['required', 'string', 'max:64'],
            'events.*.uid_text' => ['required', 'string', 'max:64'],
            'events.*.event_time' => ['required', 'date'],
            'events.*.sequence_number' => ['nullable', 'integer', 'min:0'],
            'events.*.payload' => ['nullable', 'array'],
        ]);

        $terminal->forceFill(['last_seen_at' => now()])->saveQuietly();

        $results = [];

        foreach ($data['events'] as $item) {
            $results[] = $this->ingest($terminal, $item);
        }

        return response()->json(['results' => $results]);
    }

    private function ingest($terminal, array $item): array
    {
        $eventId = (string) $item['event_id'];

        // Idempotency key: event_id unik global.
        $existing = RawRfidEvent::where('event_id', $eventId)->first();
        if ($existing) {
            return ['event_id' => $eventId, 'status' => 'duplicate', 'outcome' => $existing->outcome];
        }

        try {
            $eventTime = \Illuminate\Support\Carbon::parse($item['event_time']);
            // Clock terminal yang melenceng >24 jam ditandai; evaluasi tetap
            // memakai event_time terminal (PRD: event_time adalah sumber kebenaran).
            $timeQuality = abs($eventTime->diffInHours(now())) > 24 ? 'skewed' : 'ok';

            $event = DB::transaction(function () use ($terminal, $item, $eventId, $eventTime, $timeQuality) {
                return RawRfidEvent::create([
                    'event_id' => $eventId,
                    'terminal_id' => $terminal->id,
                    'uid_text' => $item['uid_text'],
                    'event_type' => $terminal->type === 'out' ? 'exit' : 'entry',
                    'event_time' => $eventTime,
                    'received_at' => now(),
                    'sequence_number' => $item['sequence_number'] ?? null,
                    'time_quality' => $timeQuality,
                    'payload' => $item['payload'] ?? null,
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Race: event_id dibuat request lain — anggap duplicate.
            $existing = RawRfidEvent::where('event_id', $eventId)->first();

            return ['event_id' => $eventId, 'status' => 'duplicate', 'outcome' => $existing?->outcome];
        }

        $outcome = $this->evaluator->evaluate($event);

        return ['event_id' => $eventId, 'status' => 'accepted', 'outcome' => $outcome];
    }
}
