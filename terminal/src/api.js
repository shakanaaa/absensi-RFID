/**
 * HTTP client API device v1.
 * Base URL dikonfigurasi di layar pengaturan (default server lokal XAMPP).
 */

export function getBaseUrl() {
    return localStorage.getItem('server_url') || 'http://localhost/absensi/public';
}

export function apiUrl(path) {
    return `${getBaseUrl().replace(/\/$/, '')}/api/v1/device${path}`;
}

async function request(path, { method = 'GET', token = null, body = null } = {}) {
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(apiUrl(path), {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
    });

    let data = null;
    try { data = await res.json(); } catch { /* non-JSON */ }
    return { ok: res.ok, status: res.status, data };
}

export const pair = (pairingCode) =>
    request('/pair', { method: 'POST', body: { pairing_code: pairingCode } });

export const getConfig = (token) => request('/config', { token });

export const getMe = (token) => request('/me', { token });

export const heartbeat = (token, payload) =>
    request('/heartbeat', { method: 'POST', token, body: payload });

/**
 * Kirim batch event. Server mengembalikan hasil PER EVENT —
 * event 'accepted' & 'duplicate' dianggap terkirim (idempotent).
 */
export const sendEvents = (token, events) =>
    request('/events', { method: 'POST', token, body: { events } });
