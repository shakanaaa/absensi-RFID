<?php

namespace App\Http\Middleware;

use App\Models\TerminalToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi perangkat terminal via Bearer token.
 * Token polos dikirim terminal; di DB hanya hash SHA-256 yang disimpan.
 */
class AuthenticateTerminal
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if (! $plain) {
            return response()->json(['message' => 'Token perangkat diperlukan.'], 401);
        }

        $token = TerminalToken::where('token_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->with('terminal.school')
            ->first();

        $terminal = $token?->terminal;

        if (! $token || ! $terminal || $terminal->status !== 'active') {
            return response()->json(['message' => 'Token tidak valid atau terminal nonaktif.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('terminal', $terminal);
        $request->attributes->set('terminal_token', $token);

        return $next($request);
    }
}
