# Panduan Setup Terminal RFID

Dua komponen: **aplikasi web** (backend Laravel) dan **aplikasi desktop
terminal** (Tauri 2 + Vue 3, folder `terminal/`). Reader RFID keyboard-wedge
bekerja sebagai input keyboard — tidak perlu driver khusus, tapi aplikasi
harus **tidak kehilangan fokus input**.

## A. Setup di aplikasi web (admin/petugas)

1. **Master → Terminal** → "Tambah Terminal":
   - `terminal_code` unik per sekolah, mis. `IN-01`, `OUT-01`.
   - `type`: `in` (masuk) / `out` (keluar).
   - `location`, `reader_profile` (opsional — pola UID reader).
2. Terbitkan kredensial perangkat — pilih salah satu:
   - **Kode Pairing** (disarankan): berlaku 15 menit, sekali pakai.
   - **Token Baru**: `td_...` tampil sekali — untuk provisioning manual.
3. Catat `last_seen_at` & `app_version` di halaman yang sama untuk
   memantau kesehatan terminal.

## B. Aplikasi desktop terminal (`terminal/`)

> Status: **scaffold** — frontend Vue sudah build; shell Rust belum
dikompilasi di mesin pengembangan. Uji hardware wajib sebelum go-live.

### Build & install

```bash
cd terminal
npm install
cargo install tauri-cli --version "^2"   # prerequisite Rust
npm run tauri dev        # development
npm run tauri build      # installer Windows (.msi/.exe)
```

### Pairing

1. Di web: Master → Terminal → **Kode Pairing** → salin kode `XXXX-XXXX`.
2. Di terminal: isi **URL Server** (mis. `http://192.168.1.10/absensi/public`)
   dan kode pairing → tombol **Pairing**.
3. Token disimpan lokal; aplikasi langsung memuat konfigurasi
   (pola UID reader, debounce, timezone) dari `GET /api/v1/device/config`.

### Operasi harian

- Layar utama menampilkan arah terminal (Masuk/Keluar), input scan besar,
  indikator **Online/Offline**, jumlah **antrean** lokal, dan log
  sinkronisasi.
- Scan keyboard-wedge → event langsung disimpan durable ke SQLite lokal,
  lalu disinkronkan tiap 15 detik ke `POST /api/v1/device/events`.
- Heartbeat tiap 60 detik → server mencatat `last_seen_at`, versi aplikasi,
  dan selisih jam (`clock_drift_seconds` dipakai mengoreksi `event_time`).
- **Mode Simulasi** menghasilkan UID acak untuk uji alur —
  **bukan input RFID fisik**.

### Offline & pemulihan

- Internet mati → scan tetap tersimpan di SQLite (`pending_events`);
  indikator berubah Offline; sinkronisasi otomatis saat online kembali.
- Hasil per event: `accepted`/`duplicate` dianggap terkirim (idempotent —
  `event_id` UUID mencegah dobel); event ditolak server tetap pending
  dengan `last_error` untuk diagnosis.
- Tutup aplikasi → antrean tidak hilang; restart → sinkronisasi lanjut.
- Lepas pairing tidak menghapus antrean — data tidak hilang.

## C. Reader keyboard-wedge (tanpa aplikasi desktop)

Untuk setup sederhana (browser/kiosk), halaman **Master → Registrasi
Kartu** dan input scan lain menerima ketikan keyboard — colok reader yang
bermode keyboard emulation, pastikan fokus di input scan. Sistem tidak dapat
membedakan input keyboard vs reader secara kriptografis; mitigasi: validasi
format UID, debounce 5 detik, dan kiosk mode.

## D. Penanganan masalah umum

| Gejala | Cek |
|---|---|
| Terminal 401 | Token dicabut / terminal nonaktif / pairing kedaluwarsa → terbitkan kode pairing baru |
| Event menumpuk di antrean | Server down / token invalid / event ditolak → lihat `last_error` & log sinkronisasi |
| Jam scan meleset | `clock_drift_seconds` dari heartbeat; terminal mengoreksi `event_time` lokal |
| Scan dobel | Debounce 5 detik by `event_time` — normal untuk scan berulang cepat |
| Insiden | Catat di Master → Insiden Terminal; jangan hapus event gagal |

## E. API device (ringkas)

Base: `/api/v1/device` — detail di `docs/API_DOCUMENTATION.md`.

- `POST /pair` — pairing_code → token (throttle 10/mnt)
- `GET /config`, `GET /me`, `POST /heartbeat`, `POST /events` — Bearer token
