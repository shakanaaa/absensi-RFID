# Deployment Guide

Target awal TAD: satu VPS Linux + Docker Compose (atau shared hosting +
cron). Development lokal memakai XAMPP.

## Development (saat ini)

```sh
composer install
npm install
cp .env.example .env   # set DB MySQL
php artisan key:generate
php artisan migrate
npm run build            # atau npm run dev untuk development asset
php artisan serve
```

Seed opsional untuk data awal: `php artisan db:seed` (bila seeder tersedia).

## Environment wajib produksi

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://absensi.sekolah.sch.id
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

- TLS aktif (HTTPS) — device token & session tidak boleh lewat HTTP polos.
- `APP_KEY` aman & tidak di-commit.

## Proses latar belakang (wajib)

```sh
php artisan migrate --force
php artisan schedule:work    # dev — atau cron: * * * * * php artisan schedule:run
php artisan queue:work       # bila notifikasi memakai queue
```

Scheduler menjalankan:
- `notifications:send` tiap menit (outbox WA).
- `alpa:generate` tiap hari 17:00 (hanya hari efektif).

## Aplikasi terminal desktop

Lihat `docs/TERMINAL_SETUP_GUIDE.md` — build via `terminal/` (Tauri 2).
Server terminal memakai endpoint `/api/v1/device/*` yang sama dengan web.

## Checklist pra-produksi (ringkas dari TAD §18.3)

- TLS aktif; device token unik & revoke diuji; RBAC scope diuji; backup terenkripsi.
- Uji reader/kartu semua terminal; profil reader konsisten; reader cadangan.
- Master data valid; UID tidak ganda; relasi wali terverifikasi.
- Offline/retry/clock drift/disk full diuji; SOP manual tersedia.
- Provider WA terhubung; template disetujui; retry & gagal terlihat.
- Restore drill backup berhasil; RPO/RTO dicatat.
- QA sign-off P0; UAT kepala sekolah/admin/wali kelas.
- **Belum diuji**: reader RFID fisik & provider WhatsApp resmi — wajib
  sebelum go-live.
