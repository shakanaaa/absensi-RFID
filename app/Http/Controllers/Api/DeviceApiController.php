<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevicePairingCode;
use App\Models\TerminalToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * API pairing, konfigurasi, dan heartbeat perangkat terminal.
 * Pairing TIDAK memakai Bearer token — kode pairing satu kali pakai
 * yang diterbitkan admin dari halaman master terminal.
 */
class DeviceApiController extends Controller
{
    /**
     * Tukar kode pairing → token perangkat. Token plaintext hanya
     * dikembalikan sekali di response ini.
     */
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate(['pairing_code' => ['required', 'string', 'max:32']]);

        $code = DevicePairingCode::where('code_hash', hash('sha256', strtoupper(trim($data['pairing_code']))))
            ->with('terminal')
            ->first();

        if (! $code || ! $code->isUsable() || $code->terminal->status !== 'active') {
            return response()->json(['message' => 'Kode pairing tidak valid atau sudah kedaluwarsa.'], 422);
        }

        $plain = 'td_'.Str::random(48);

        $token = DB::transaction(function () use ($code, $plain) {
            $code->forceFill(['used_at' => now()])->save();

            return TerminalToken::create([
                'terminal_id' => $code->terminal_id,
                'token_hash' => hash('sha256', $plain),
                'label' => 'Paired '.now()->format('Y-m-d H:i'),
            ]);
        });

        return response()->json([
            'token' => $plain,
            'terminal' => [
                'id' => $code->terminal->id,
                'code' => $code->terminal->terminal_code,
                'type' => $code->terminal->type,
                'location' => $code->terminal->location,
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Konfigurasi efektif terminal: reader profile, debounce, timezone. */
    public function config(Request $request): JsonResponse
    {
        $terminal = $request->attributes->get('terminal');
        $profile = $terminal->readerProfile;

        return response()->json([
            'terminal' => [
                'id' => $terminal->id,
                'code' => $terminal->terminal_code,
                'type' => $terminal->type,
                'location' => $terminal->location,
            ],
            'reader' => $profile ? [
                'profile_id' => $profile->id,
                'name' => $profile->name,
                'pattern' => $profile->pattern,
                'uid_length' => $profile->uid_length,
                'suffix' => $profile->suffix,
            ] : null,
            'rules' => [
                'debounce_seconds' => 5,
                'max_batch_size' => 500,
                'event_types' => ['entry', 'exit'],
            ],
            'timezone' => $terminal->school->timezone ?? config('app.timezone'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Heartbeat: perbarui last_seen + versi aplikasi, laporkan
     * clock drift perangkat agar klien bisa mengoreksi event_time.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $terminal = $request->attributes->get('terminal');

        $data = $request->validate([
            'app_version' => ['nullable', 'string', 'max:32'],
            'queue_pending' => ['nullable', 'integer', 'min:0'],
            'device_time' => ['nullable', 'date'],
        ]);

        $terminal->forceFill([
            'last_seen_at' => now(),
            'app_version' => $data['app_version'] ?? $terminal->app_version,
        ])->save();

        $drift = isset($data['device_time'])
            ? (int) \Illuminate\Support\Carbon::parse($data['device_time'])->diffInSeconds(now(), false)
            : null;

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'clock_drift_seconds' => $drift,
            'queue_pending_received' => $data['queue_pending'] ?? null,
        ]);
    }
}
