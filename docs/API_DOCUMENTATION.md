# API Documentation

Status: Draft aktif. Base path `/api/v1` sudah diimplementasikan untuk device terminal; endpoint portal memakai Inertia (bukan REST).

## Konvensi (dari TAD)

- Base path `/api/v1`, HTTPS, JSON.
- Timestamp ISO-8601 dengan offset; UUID untuk event_id.
- `request_id`/correlation id per request.
- Error code stabil (DEVICE_REVOKED, UNKNOWN_TERMINAL, PROFILE_MISMATCH, UID_FORMAT_INVALID, EVENT_DUPLICATE, CARD_UNKNOWN, CARD_INACTIVE, STATE_CONFLICT, CLOCK_DRIFT_REVIEW, RATE_LIMITED).

## Endpoint yang direncanakan

### Device (terminal RFID)

| Method | Endpoint | Auth |
|---|---|---|
| POST | `/api/v1/device/session` | pairing secret sekali pakai → device token |
| GET | `/api/v1/device/config` | device token |
| POST | `/api/v1/device/events/batch` | device token; hasil per event |
| POST | `/api/v1/device/heartbeat` | device token |
| GET | `/api/v1/device/sync/status` | device token |
| POST | `/api/v1/device/registration/scans` | device token REG + admin session |

### Portal (Inertia web + Sanctum opsional)

Belum ada endpoint domain. Direncanakan per domain: auth, master (students/classes/guardians), rfid (cards/assignments/bulk-registration), attendance, requests, schedule/early-dismissals, calendar, reporting, ops (terminals/incidents/sync-conflicts).

## Endpoint yang ada saat ini

- `GET /api/user` — Sanctum, return user terautentikasi (bawaan Sanctum).

### Device API (prefix `/api/v1/device`)

Auth perangkat: `Authorization: Bearer td_...` (token diterbitkan via pairing atau manual oleh admin). Token disimpan sebagai hash SHA-256; plaintext hanya tampil sekali.

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| POST | `/pair` | kode pairing | Tukar kode pairing (15 mnt, sekali pakai) → `{token, terminal, server_time}`. Throttle 10/menit. |
| GET | `/me` | device token | Info terminal + `server_time`. |
| GET | `/config` | device token | Reader profile (pattern, uid_length, suffix), rules (debounce 5 dtk, max batch 500), timezone sekolah. |
| POST | `/heartbeat` | device token | Body: `app_version`, `queue_pending`, `device_time`. Update `last_seen_at`+`app_version`; response `server_time` + `clock_drift_seconds`. |
| POST | `/events` | device token | Batch ingest. Body `{events:[{event_id, uid_text, event_time, sequence_number?, payload?}]}` (maks 500). Response `{results:[{event_id,status,outcome}]}` — per event; `status`: `accepted`/`duplicate`/`rejected`. Throttle 240/menit. |

Catatan implementasi:
- `event_id` unik global → duplicate tidak diproses ulang (idempotent).
- `event_time` terminal adalah sumber kebenaran evaluasi; selisih >24 jam → `time_quality=skewed`.
- Raw event append-only; koreksi lewat `manual_adjustments`/review, bukan edit/hapus event.
- Clock drift dilaporkan via heartbeat; terminal mengoreksi `event_time` lokal.
- Endpoint `/registration/scans` belum diimplementasikan (registrasi massal saat ini via keyboard-wedge web); menunggu keputusan BD-08.
