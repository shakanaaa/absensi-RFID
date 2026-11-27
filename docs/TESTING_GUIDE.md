# Testing Guide

## Menjalankan test

```sh
php artisan test          # seluruh suite (SQLite in-memory)
php artisan test --filter=NamaTest
vendor/bin/pint           # format Laravel Pint
npm run build             # build frontend (wajib sebelum test Inertia-render)
```

## Layer test yang ada saat ini

| Layer | File | Cakupan |
|---|---|---|
| Feature/Auth | `tests/Feature/Auth/*` | login, registrasi, reset password |
| Feature/Master | `MasterDataTest` | CRUD master data + scope |
| Feature/Card | `CardManagementTest` | assign/unassign/kartu nonaktif + registrasi massal (pause/resume/skip/cancel, auto-next) |
| Feature/Device API | `DeviceApiTest` | token, idempotency, pairing, config, heartbeat, evaluator via API |
| Feature/Attendance | `AttendanceEdgeCaseTest` | libur, debounce, proteksi status, clock skew, review + filter/detail |
| Feature/Request & Alpa | `RequestAndAlpaTest` | draft→submit→decide, self-decide ditolak, alpa Opsi B, hari efektif |
| Feature/Schedule | `ScheduleTest` | draft→approve four-eyes, supersede, pulang awal |
| Feature/Notification | `NotificationTest` | outbox idempotency, retry, trigger |
| Feature/Report | `ReportTest` | formula BD-12, hari efektif, scope, export CSV |
| Feature/Calendar | `CalendarTest` | 3 view, scoping role |
| Feature/Incident | `TerminalIncidentTest` | buka/resolve insiden + otorisasi |
| Feature/Profile | `ProfileTest` | profil user |

Total suite: **101 test** (per `php artisan test`).

## Cara menguji per modul (manual)

- **Login/RBAC**: login tiap role → cek menu & akses sesuai
  `ROLE_PERMISSION_MATRIX.md`.
- **Registrasi massal**: buat sesi → scan UID → auto-next; coba Jeda,
  Skip, Batalkan.
- **Terminal API**: `curl -X POST /api/v1/device/events -H "Authorization:
  Bearer td_..." -d '{"events":[...]}'` — kirim event_id sama 2× →
  `duplicate`.
- **Anomali**: picu OUT tanpa IN via API → muncul di Perlu Konfirmasi →
  resolve dengan alasan.
- **Alpa**: `php artisan alpa:generate` → verifikasi wali → konfirmasi admin.
- **Laporan**: `/reports` + filter + "Ekspor CSV".

## Yang belum diuji otomatis

- Reader RFID fisik (keyboard-wedge vs scanner serial).
- Provider WhatsApp resmi (mock saja yang diuji).
- Aplikasi Tauri desktop (frontend build OK; Rust belum dikompilasi).
- Performa burst (butuh load test terpisah).
