# Terminal RFID Desktop (Tauri 2 + Vue 3)

Aplikasi edge client untuk terminal RFID absensi — **offline-first**.
Folder ini terpisah dari backend Laravel; dependency tidak bercampur.

> **Status: scaffold.** Belum dikompilasi (`cargo`/`rustc` tidak tersedia di
> mesin ini) dan **belum diuji dengan reader RFID fisik**. Mode simulasi
> tersedia untuk pengujian alur — tidak merepresentasikan input fisik.

## Arsitektur

```
terminal/
├── src/                  # Vue 3 frontend
│   ├── App.vue           # pairing → mode IN/OUT → layar scan + monitor antrean
│   ├── api.js            # HTTP client /api/v1/device (pair, config, heartbeat, events)
│   ├── db.js             # SQLite lokal via @tauri-apps/plugin-sql (antrean durable)
│   └── sync.js           # loop sinkronisasi + heartbeat + clock drift
└── src-tauri/            # Rust shell Tauri 2
    ├── Cargo.toml
    ├── tauri.conf.json
    ├── capabilities/default.json
    └── src/{main,lib}.rs
```

## Cara kerja offline-first

1. Scan keyboard-wedge → `enqueueEvent()` menulis ke SQLite **sebelum**
   dianggap tercatat (event_id UUID → retry idempotent di server).
2. `syncOnce()` tiap 15 detik mengirim batch ≤500 event; hasil **per event**
   (`accepted`/`duplicate` = terkirim, lainnya tetap pending + `last_error`).
3. Heartbeat tiap 60 detik → `last_seen_at`, `app_version`, dan
   `clock_drift_seconds` yang dipakai mengoreksi `event_time` lokal.
4. Aplikasi restart → antrean `pending_events` di SQLite tetap ada dan
   tersinkron otomatis saat online.

## Setup & build

```bash
cd terminal
npm install
cargo install tauri-cli --version "^2"   # sekali
npm run tauri dev        # development
npm run tauri build      # installer (.msi/.exe)
```

## Pairing

1. Di web app: Master → Terminal → **Kode Pairing** (berlaku 15 menit).
2. Di terminal: masukkan URL server + kode → token `td_...` diterima sekali
   dan disimpan di SQLite `settings`.
3. Token dapat dilepas via tombol "Lepas Pairing" — antrean event
   **tidak dihapus**.

## Mode simulasi

Tombol "Mode Simulasi" menghasilkan UID acak untuk menguji alur
antrean/sinkronisasi tanpa hardware. **Bukan input RFID fisik.**

## API yang dipakai

| Endpoint | Fungsi |
|---|---|
| `POST /api/v1/device/pair` | tukar kode pairing → token |
| `GET  /api/v1/device/config` | reader profile, debounce, timezone |
| `POST /api/v1/device/heartbeat` | last_seen + clock drift |
| `POST /api/v1/device/events` | batch ingest idempotent |

Lihat `docs/API_DOCUMENTATION.md` untuk kontrak lengkap.

## Yang belum dikerjakan / keterbatasan

- Icon installer & penandatanganan bundle.
- Auto-update (tauri-plugin-updater).
- Mode REG (registrasi massal via terminal) — menunggu BD-08.
- Pengujian dengan reader fisik — **wajib dilakukan di lapangan**.
