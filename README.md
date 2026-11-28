# Sistem Absensi RFID — SMK

Aplikasi absensi siswa berbasis RFID untuk sekolah. Siswa tap kartu RFID di halaman **Scan Absensi** (keyboard-wedge reader), status hadir/terlambat dihitung otomatis dari jam masuk, dan rekap harian bisa dikirim ke WhatsApp.

- **Role**: Admin Sekolah & Petugas Piket
- **Stack**: Laravel 13 + Inertia.js + Vue 3, MySQL

## Kebutuhan

- PHP 8.5+
- Composer 2.x
- Node.js 20+ & npm
- MySQL / MariaDB (XAMPP)

## Instalasi

```bash
# 1. Install dependency PHP & frontend
composer install
npm install

# 2. Setup environment
cp .env.example .env
php artisan key:generate
```

Sesuaikan `.env` — koneksi database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absensi
DB_USERNAME=root
DB_PASSWORD=
```

Buat database `absensi` dulu di phpMyAdmin / MySQL, lalu:

```bash
# 3. Migrasi + data awal (opsional — berisi data demo)
php artisan migrate:fresh --seed

# 4. Build frontend
npm run build

# 5. Jalankan aplikasi
php artisan serve        # http://localhost:8000
```

Kalau mau development frontend dengan hot reload, jalankan `npm run dev` di terminal terpisah.

## Menjalankan Aplikasi

```bash
php artisan serve
```

Buka http://localhost:8000 — login dengan akun yang dibuat lewat command di bawah.

### Supaya libur nasional & laporan otomatis berjalan

Scheduler Laravel perlu dipicu tiap menit. Pilih salah satu:

```bash
# Opsi 1: terminal khusus (biarkan tetap terbuka)
php artisan schedule:work

# Opsi 2: Windows Task Scheduler — jadwalkan tiap menit:
#   Program: C:\xampp\php\php.exe
#   Argumen: C:\xampp\htdocs\apk_absensi\artisan schedule:run
```

## Command Artisan Penting

### Buat akun

```bash
php artisan admin:create <username>
php artisan piket:create <username>
```

Contoh non-interaktif:

```bash
php artisan admin:create budi --name="Budi Santoso" --password=rahasia123
php artisan piket:create ani --name="Ani Guru" --password=rahasia123
```

Opsi: `--name=`, `--password=`, `--school=<id>`.

### Sinkron libur nasional

```bash
php artisan holidays:sync              # semua sekolah, semua tahun ajaran
php artisan holidays:sync --school=1   # satu sekolah saja
```

Idempotent — aman dijalankan berulang. Sudah dijadwalkan harian via scheduler; libur juga otomatis terisi saat tahun ajaran baru dibuat.

### Command bawaan Laravel yang sering dipakai

```bash
php artisan migrate:fresh --seed   # reset DB + data demo
php artisan test                   # jalankan semua test
npm run build                      # build frontend produksi
php artisan route:list             # daftar semua route
php artisan about                  # info environment
```

## Alur Pakai Sehari-hari

1. **Login** → `admin` atau `guru.piket` (password diatur saat `admin:create`/`piket:create`)
2. **Master Data** → isi Tahun Ajaran, Tingkat, Konsentrasi, Kelas, Siswa, Kartu RFID
3. **Scan Absensi** → buka di PC yang tersambung reader RFID, dekatkan kartu siswa
4. **Absensi** → pantau status per kelas per tanggal
5. **Laporan** → rekap harian + tombol kirim ke WhatsApp
6. **Kenaikan Kelas** → buat tahun ajaran baru (otomatis 10 Jul – 9 Jun) lalu promosikan
7. **Pengaturan** → jam masuk/pulang, batas terlambat, nomor WA, konten laporan, libur mingguan & kalender libur

## Catatan Teknis

- Reader RFID harus mode **keyboard wedge** (ketik UID + Enter). Halaman Scan Absensi menangkap input keyboard.
- Jam disimpan UTC di database; perhitungan status memakai timezone sekolah (`Asia/Jakarta`).
- Test: `php artisan test` (pakai SQLite in-memory).
