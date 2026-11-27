# Panduan Pengguna — Sistem Absensi RFID

Panduan ini per role. Semua label status menggunakan bahasa Indonesia.

## 1. Login & Akun

- Buka `/login`, masukkan email + password.
- Lupa password: `/forgot-password` → ikuti tautan reset di email.
- Akun `suspended` tidak dapat login. Hubungi admin untuk aktivasi.
- **Penting**: pengaju permohonan tidak dapat menyetujui permohonannya sendiri;
  pembuat versi jadwal tidak dapat menyetujuinya sendiri (dicek server).

## 2. Admin Sekolah

### Master data
- **Tahun ajaran / Semester / Tingkat / Kelas / Siswa**: menu Master → CRUD.
  Perubahan keanggotaan kelas dicatat sebagai histori (`from_date`/`to_date`),
  tidak menimpa riwayat.
- **Orang tua / Wali**: tautkan orang tua ke siswa, lalu verifikasi relasi
  (hanya anak terverifikasi yang bisa melihat data siswa).
- **Pengguna**: kelola akun & role (`/users`).

### Kartu RFID
- Master → **Kartu RFID**: tambah kartu (UID string, leading zero aman),
  assign/unassign, blokir/nonaktifkan. Satu kartu = satu assignment aktif;
  satu siswa = satu kartu aktif. Riwayat assignment tersimpan.

### Registrasi massal
1. Master → Registrasi Kartu → buat sesi untuk sebuah kelas.
2. Sistem otomatis memilih siswa pertama tanpa kartu aktif.
3. Scan kartu via keyboard-wedge (fokus input otomatis dijaga).
4. Setelah scan, sistem **otomatis pindah ke siswa berikutnya**.
5. Tombol: **Jeda/Lanjutkan**, **Skip Siswa Ini**, **Tutup Sesi**, **Batalkan Sesi**.
   - Skip/catatan dicatat di riwayat scan (append-only).
   - Batalkan sesi **tidak menghapus** kartu yang sudah tertaut.
6. Progress: terdaftar / total / sisa antrean / di-skip.

### Terminal RFID
- Master → **Terminal**: daftarkan terminal (kode unik per sekolah, arah
  Masuk/Keluar, profil reader opsional, status aktif/nonaktif/maintenance).
- **Token Baru**: token `td_...` hanya tampil sekali — simpan di perangkat.
- **Kode Pairing**: kode `XXXX-XXXX` berlaku 15 menit untuk pairing aplikasi
  desktop terminal (lihat TERMINAL_SETUP_GUIDE).
- Token dapat dicabut kapan saja; terminal langsung tidak bisa upload.

### Insiden terminal
- Master → **Insiden Terminal**: catat gangguan (kategori: terminal tidak
  aktif, token dicabut, gagal sinkronisasi, event invalid, clock drift,
  event tertunda, kesalahan komunikasi, pemulihan), lalu selesaikan setelah
  pulih. Semua tercatat di audit log.

### Jadwal & pulang cepat
- Master → **Jadwal**: buat jadwal draft → setujui (approval dua tahap;
  pembuat tidak bisa approve sendiri). Versi baru pada tanggal yang sama
  membatalkan versi approved lama.
- **Pulang cepat** = jadwal resmi bertipe `pulang_awal`; evaluator absensi
  langsung memakai jadwal efektif terbaru **untuk event baru** — histori
  tidak berubah.

### Laporan
- `/reports`: filter rentang tanggal + kelas.
  - Rekap per kelas & per siswa; % kehadiran = (Hadir+Terlambat)/hari efektif.
  - **Ekspor CSV** (UTF-8, kompatibel Excel).

## 3. Kepala Sekolah / Wakil Kepala Sekolah

- Dashboard: rekap sekolah, **tren kehadiran 7 hari**, **perbandingan kelas
  hari ini**.
- Laporan: akses seluruh sekolah (formula BD-12).
- Kalender akademik: `/calendar` view bulan/minggu/hari.
- Persetujuan jadwal & verifikasi Alpa sesuai kewenangan.

## 4. Wali Kelas

- Dashboard: kelas binaan + siswa bermasalah hari ini (terlambat/alpa).
- Kalender & laporan dibatasi ke siswa kelas binaan.
- **Alpa** (`/attendance/alpa`): verifikasi `alpa_menunggu` siswa kelas
  binaan → lanjut ke konfirmasi admin.
- Permohonan: lihat & setujui permohonan siswa kelas binaan sesuai policy.

## 5. Petugas Piket

- Dashboard: jumlah tap hari ini, status terminal (last seen, versi app).
- **Perlu Konfirmasi** (`/master/attendance/review`): tangani sesi
  `perlu_konfirmasi` — filter tanggal/kelas/terminal, lihat detail
  pergerakan & event mentah, lalu konfirmasi dengan **alasan wajib**
  (menulis `manual_adjustments` + audit; event mentah tidak diubah).
- Operasikan registrasi massal & terminal (sama seperti admin untuk modul tsb).

## 6. Orang Tua

- Hanya melihat anak dengan relasi **terverifikasi**.
- Dashboard: status hari ini tiap anak + tautan kalender.
- Kalender anak: `/calendar` (status absen, jadwal, libur, kegiatan).
- Permohonan (`/requests`): ajukan izin/sakit/dispensasi, pantau status
  keputusan; notifikasi WA dikirim saat diputuskan.
- Riwayat & rekap anak tersedia di laporan (scope otomatis).

## 7. Siswa

- Dashboard menampilkan akun; akses detail siswa sendiri mengikuti
  kebijakan yang ada (belum ada tautan siswa↔user — lihat CHANGELOG).

## 8. Alpa (alur Opsi B)

1. `php artisan alpa:generate` (terjadwal 17:00) membuat `alpa_menunggu`
   **hanya pada hari efektif** — libur/tahun ajaran non-aktif dilewati.
2. Wali kelas verifikasi → admin konfirmasi → status `alpa` + audit +
   notifikasi WA ke wali verified.
3. Tidak ada Alpa otomatis final; hari yang belum selesai tidak
   difinalisasi.

## 9. Notifikasi

- Outbox `notification_outbox` + `notifications:send` tiap menit.
- Trigger saat ini: permohonan approved/rejected → pengaju;
  alpa confirmed → wali verified (bila punya `users.phone`).
- Provider default **mock** (development). Provider WA resmi tinggal
  mengganti binding `WhatsAppProvider` di `AppServiceProvider` — jangan
  hardcode secret.
