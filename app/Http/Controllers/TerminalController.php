<?php

namespace App\Http\Controllers;

use App\Http\Requests\TerminalRequest;
use App\Models\Terminal;
use App\Models\TerminalToken;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TerminalController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Terminal::class);

        $terminals = Terminal::where('school_id', $request->user()->school_id)
            ->with(['readerProfile:id,name', 'tokens' => fn ($q) => $q->latest()->limit(3)])
            ->orderBy('terminal_code')
            ->get()
            ->each(function (Terminal $t) {
                // Heartbeat sinkron tiap 60 dtk; online bila terlihat < 5 menit.
                $t->is_online = $t->last_seen_at !== null
                    && $t->last_seen_at->gte(now()->subMinutes(5));
            });

        return Inertia::render('Master/Terminals/Index', [
            'terminals' => $terminals,
            'readerProfiles' => \App\Models\ReaderProfile::where('is_active', true)->get(['id', 'name']),
            'newToken' => $request->session()->get('new_token'),
            'newPairingCode' => $request->session()->get('new_pairing_code'),
            'pairingTerminal' => $request->session()->get('pairing_terminal'),
        ]);
    }

    public function store(TerminalRequest $request): RedirectResponse
    {
        $this->authorize('create', Terminal::class);

        Terminal::create($request->safe()->except('status') + [
            'school_id' => $request->user()->school_id,
            'status' => $request->validated('status', 'active'),
        ]);

        AuditLogger::log('terminal.created', Terminal::class, null, after: $request->safe()->except('reader_profile_id'));

        return redirect()->route('master.terminals.index')->with('success', 'Terminal didaftarkan.');
    }

    public function update(TerminalRequest $request, Terminal $terminal): RedirectResponse
    {
        $this->authorize('update', $terminal);

        $terminal->update($request->safe()->except('terminal_code'));

        AuditLogger::log('terminal.updated', Terminal::class, $terminal);

        return redirect()->route('master.terminals.index')->with('success', 'Terminal diperbarui.');
    }

    /** Terbitkan token baru — plaintext hanya tampil sekali di response. */
    public function issueToken(Request $request, Terminal $terminal): RedirectResponse
    {
        $this->authorize('update', $terminal);

        $plain = 'td_'.Str::random(48);

        $token = $terminal->tokens()->create([
            'token_hash' => hash('sha256', $plain),
            'label' => 'Token #'.($terminal->tokens()->count() + 1),
        ]);

        AuditLogger::log('terminal.token_issued', TerminalToken::class, $token, after: ['terminal_id' => $terminal->id]);

        // Tampilkan sekali via session flash — jangan disimpan.
        return redirect()->route('master.terminals.index')
            ->with('success', 'Token diterbitkan.')
            ->with('new_token', $plain);
    }

    /**
     * Terbitkan kode pairing satu kali pakai (15 menit) untuk diinput
     * di aplikasi terminal. Kode plaintext hanya tampil sekali.
     */
    public function createPairingCode(Request $request, Terminal $terminal): RedirectResponse
    {
        $this->authorize('update', $terminal);

        // Format XXXX-XXXX dari alphabet tanpa karakter ambigu.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $raw = '';
        for ($i = 0; $i < 8; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $code = substr($raw, 0, 4).'-'.substr($raw, 4);

        \App\Models\DevicePairingCode::create([
            'terminal_id' => $terminal->id,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(15),
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log('terminal.pairing_code_issued', Terminal::class, $terminal);

        return redirect()->route('master.terminals.index')
            ->with('success', 'Kode pairing diterbitkan (berlaku 15 menit).')
            ->with('new_pairing_code', $code)
            ->with('pairing_terminal', $terminal->terminal_code);
    }

    public function revokeToken(Request $request, TerminalToken $token): RedirectResponse
    {
        $this->authorize('update', $token->terminal);

        $token->update(['revoked_at' => now()]);

        AuditLogger::log('terminal.token_revoked', TerminalToken::class, $token);

        return redirect()->route('master.terminals.index')->with('success', 'Token dicabut.');
    }
}
