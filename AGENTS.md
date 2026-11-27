# MASTER PROMPT — AUDIT DAN FINALISASI BUSINESS RULES SISTEM ABSENSI RFID

Kamu bertugas sebagai **System Analyst, Business Analyst, Software Architect, dan QA Analyst** untuk proyek Sistem Absensi Sekolah Berbasis RFID.

Sebelum melanjutkan implementasi fitur, lakukan audit menyeluruh dan finalisasi seluruh aturan bisnis sistem.

Tujuan utama:
- Tidak ada aturan bisnis yang saling bertentangan.
- Tidak ada proses yang memiliki perilaku ambigu.
- Setiap kondisi memiliki hasil yang jelas.
- Setiap perubahan data memiliki jejak audit.
- Setiap aturan memiliki acceptance criteria dan test scenario.
- Sistem tidak mengambil keputusan administratif secara otomatis tanpa dasar kewenangan.

## 1. Sumber Kebenaran

Periksa seluruh sumber berikut:

1. Keputusan eksplisit yang telah disetujui pengguna.
2. PRD terbaru.
3. TAD terbaru.
4. Dokumentasi aturan bisnis yang sudah tersedia.
5. Struktur database, migration, model, service, controller, dan kode yang sudah ada.

Jika terdapat perbedaan:
- Keputusan eksplisit pengguna menjadi prioritas.
- Jangan menganggap implementasi kode saat ini otomatis benar.
- Jangan mengubah keputusan bisnis secara sepihak.
- Catat konflik dan berikan rekomendasi penyelesaian.

Jangan mengarang kebijakan sekolah yang belum ditentukan.

## 2. Kelompok Aturan Bisnis yang Wajib Diaudit

### A. Identitas dan Hak Akses
Periksa aturan untuk:
- Super Admin.
- Admin Sekolah.
- Kepala Sekolah.
- Guru.
- Wali Kelas.
- Petugas Piket.
- Siswa.
- Orang Tua/Wali.
- Petugas Pengelola RFID.

Tentukan:
- Hak melihat data.
- Hak menambah data.
- Hak mengubah data.
- Hak menghapus atau menonaktifkan data.
- Hak menyetujui dan menolak permohonan.
- Batas kewenangan berdasarkan kelas atau lingkup sekolah.
- Pemisahan tugas agar satu orang tidak dapat menyalahgunakan seluruh proses.

Jangan memberikan kewenangan hanya karena suatu halaman perlu diakses.

### B. Registrasi dan Kepemilikan Kartu RFID
Tentukan aturan:
- Registrasi kartu baru.
- Registrasi kartu yang belum memiliki pemilik.
- Penggantian kartu hilang atau rusak.
- Penonaktifan kartu.
- Satu kartu tidak boleh dimiliki dua siswa aktif.
- Satu siswa tidak boleh memiliki beberapa kartu aktif tanpa kebijakan yang jelas.
- Riwayat kepemilikan kartu.
- Pemindaian UID duplikat.
- Pembatalan registrasi.
- Registrasi massal per kelas.
- Pemulihan proses registrasi yang terputus.

Registrasi massal harus mendukung:
- Antrean siswa yang belum memiliki kartu aktif.
- Pemindaian kartu satu per satu.
- Otomatis berpindah ke siswa berikutnya setelah berhasil.
- Pause, resume, skip, dan cancel.
- Indikator progres.
- Penanganan kartu yang sudah terdaftar.
- Penanganan pembacaan UID gagal.

Jangan menghapus riwayat registrasi yang sudah berhasil secara diam-diam.

### C. Aturan Kehadiran
Tetapkan perilaku untuk:
- Belum Masuk.
- Hadir.
- Terlambat.
- Tidak Hadir.
- Izin.
- Sakit.
- Dispensasi.
- Pulang.
- Pulang Awal.
- Pulang Belum Tercatat.

Periksa:
- Kapan kehadiran mulai dihitung.
- Kapan keterlambatan ditentukan.
- Bagaimana status selama hari sekolah masih berlangsung.
- Bagaimana status ketika jadwal berubah.
- Bagaimana perlakuan hari libur.
- Bagaimana penanganan siswa yang tidak melakukan tap IN.
- Bagaimana penanganan siswa yang tidak melakukan tap OUT.

Jangan menetapkan siswa Alpa hanya karena hari sekolah belum selesai.

Jangan membuat waktu OUT fiktif apabila siswa tidak melakukan tap OUT.

### D. Pergerakan Siswa
Pisahkan status kehadiran dari aktivitas pergerakan siswa.

Audit seluruh transisi:
- Belum Masuk → Di Sekolah.
- Di Sekolah → Keluar Sementara.
- Keluar Sementara → Masuk Kembali.
- Di Sekolah → Sudah Pulang.
- Di Sekolah → Pulang Belum Tercatat.

Tentukan:
- Tap IN berulang.
- Tap OUT berulang.
- Tap OUT tanpa tap IN.
- Keluar sementara.
- Kembali setelah keluar sementara.
- Keluar tanpa izin.
- Kembali melewati waktu yang diharapkan.
- Peristiwa yang terjadi di terminal berbeda.
- Event yang diterima terlambat akibat sinkronisasi offline.

Setiap peristiwa RFID tetap disimpan sebagai catatan mentah. Evaluasi validitasnya dilakukan terpisah.

### E. Izin dan Persetujuan
Tentukan aturan untuk:
- Izin pribadi.
- Sakit.
- Keperluan keluarga.
- Keperluan mendesak.
- Izin keluar sementara.
- Izin pulang awal.
- Dispensasi kegiatan sekolah.

Tentukan:
- Siapa yang dapat mengajukan.
- Siapa yang dapat memeriksa.
- Siapa yang dapat menyetujui.
- Siapa yang dapat menolak.
- Siapa yang dapat membatalkan.
- Bukti atau dokumen yang diperlukan.
- Batas waktu pengajuan.
- Penanganan permohonan darurat.
- Riwayat keputusan.
- Mekanisme sanggahan orang tua.

Jangan menyamakan izin pribadi dengan dispensasi kegiatan sekolah.

Petugas piket tidak otomatis memiliki kewenangan menyetujui seluruh jenis permohonan.

### F. Pulang Cepat dan Perubahan Jadwal Sekolah
Perlakukan pulang cepat sebagai perubahan jadwal resmi, bukan sekadar izin pulang massal.

Dukung cakupan:
- Seluruh sekolah.
- Tingkat kelas.
- Kelas tertentu.

Tentukan:
- Pihak yang berwenang membuat perubahan jadwal.
- Pihak yang menyetujui.
- Waktu mulai berlaku.
- Waktu pulang baru.
- Alasan perubahan.
- Pengumuman kepada orang tua.
- Pembatalan perubahan jadwal.
- Perubahan mendadak.
- Riwayat versi jadwal.
- Penanganan siswa yang pulang berdasarkan jadwal lama.
- Penanganan siswa yang pulang sebelum waktu baru tanpa izin.

Perubahan jadwal tidak boleh mengubah fakta kehadiran historis secara diam-diam.

### G. Kalender dan Hari Efektif
Audit:
- Kalender akademik.
- Hari sekolah.
- Hari libur.
- Ujian.
- Kegiatan sekolah.
- Jadwal khusus.
- Perubahan jadwal kelas.
- Izin beberapa hari.

Pastikan prioritas jadwal ditentukan secara konsisten.

Hari libur tidak boleh menghasilkan status Alpa.

Izin beberapa hari hanya dihitung pada hari sekolah efektif sesuai jadwal yang berlaku.

### H. Notifikasi
Tentukan aturan notifikasi untuk:
- Tap IN berhasil.
- Tap OUT berhasil.
- Tidak ada kehadiran.
- Keterlambatan.
- Izin diajukan.
- Izin disetujui atau ditolak.
- Pulang awal.
- Perubahan jadwal.
- Koreksi data kehadiran.

Tentukan:
- Penerima notifikasi.
- Kanal notifikasi.
- Pengaturan notifikasi.
- Pencegahan notifikasi ganda.
- Penanganan pengiriman gagal.
- Pengiriman ulang.
- Notifikasi koreksi jika data yang sudah diberitahukan berubah.

Sistem tidak boleh menyatakan pesan berhasil dikirim apabila provider gagal mengirimkannya.

### I. Koreksi dan Audit
Tentukan:
- Siapa yang boleh mengoreksi kehadiran.
- Alasan koreksi wajib.
- Bukti pendukung.
- Persetujuan koreksi.
- Riwayat nilai sebelum dan sesudah koreksi.
- Identitas pengguna yang melakukan perubahan.
- Waktu perubahan.
- Riwayat keputusan.
- Penanganan kesalahan administrator.

Jangan menghapus event RFID mentah untuk memperbaiki status kehadiran.

### J. Terminal dan Sinkronisasi
Tentukan:
- Identitas setiap terminal.
- Terminal IN dan OUT.
- Terminal registrasi.
- Status online/offline.
- Penyimpanan event lokal.
- Pengiriman event ke server.
- Event duplikat.
- Event terlambat.
- Perbedaan waktu perangkat.
- Kegagalan sinkronisasi.
- Pemulihan setelah terminal hidup kembali.

Gunakan event ID unik dan mekanisme idempotensi.

Event yang belum tersinkronisasi tidak boleh dianggap sudah final.

### K. Laporan dan Perhitungan
Audit:
- Rekap kehadiran harian.
- Rekap per kelas.
- Rekap per siswa.
- Rekap keterlambatan.
- Rekap izin dan sakit.
- Rekap pulang awal.
- Rekap ketidakhadiran.
- Persentase kehadiran.
- Laporan periode.
- Ekspor data.

Rumus persentase kehadiran harus memiliki definisi resmi.

Jangan menganggap izin atau sakit sebagai kehadiran fisik.

Pastikan total rekap dapat direkonsiliasi dengan jumlah siswa aktif pada tanggal laporan.

## 3. Format Dokumentasi Aturan

Buat file:

`docs/BUSINESS_RULES.md`

Setiap aturan wajib menggunakan format:

- ID aturan.
- Nama aturan.
- Modul.
- Deskripsi.
- Kondisi pemicu.
- Syarat.
- Proses sistem.
- Hasil yang diharapkan.
- Pengecualian.
- Hak akses yang terlibat.
- Dampak terhadap data.
- Acceptance criteria.
- Test scenario.
- Status: Confirmed / Proposed / Needs Decision.

Contoh ID:
- AUTH-001
- RFID-001
- ATT-001
- MOV-001
- LEAVE-001
- EARLY-001
- CAL-001
- NOTIF-001
- AUDIT-001
- REPORT-001

## 4. Daftar Keputusan yang Belum Final

Buat file:

`docs/BUSINESS_DECISIONS.md`

Masukkan semua kebijakan yang belum memiliki keputusan final.

Untuk setiap keputusan, tuliskan:
- ID keputusan.
- Permasalahan.
- Pilihan yang tersedia.
- Rekomendasi.
- Dampak setiap pilihan.
- Risiko jika tidak diputuskan.
- Pihak yang perlu memberikan persetujuan.
- Status keputusan.

Jangan mengubah status Proposed menjadi Confirmed tanpa persetujuan pengguna.

Jika ada aturan penting yang belum jelas, prioritaskan pertanyaan yang paling berdampak. Jangan menanyakan puluhan hal sekaligus.

## 5. Matriks Transisi Status

Buat:

`docs/STATE_TRANSITION_MATRIX.md`

Dokumentasikan seluruh transisi status kehadiran dan pergerakan siswa.

Untuk setiap transisi, cantumkan:
- Status awal.
- Event pemicu.
- Syarat.
- Status akhir.
- Apakah valid.
- Penanganan jika tidak valid.
- Dampak ke laporan.
- Dampak ke notifikasi.
- Test scenario.

Tidak boleh ada transisi yang tidak terdokumentasi.

## 6. Matriks Hak Akses

Buat:

`docs/ROLE_PERMISSION_MATRIX.md`

Dokumentasikan seluruh role dan izin per modul.

Pastikan implementasi otorisasi menggunakan mekanisme backend, bukan hanya menyembunyikan tombol di frontend.

## 7. Sinkronisasi dengan Implementasi

Setelah dokumentasi selesai:

1. Cocokkan business rules dengan database.
2. Cocokkan business rules dengan model dan relasi.
3. Cocokkan business rules dengan service/action.
4. Cocokkan business rules dengan controller dan API.
5. Cocokkan business rules dengan frontend.
6. Cocokkan business rules dengan automated tests.

Buat:

`docs/BUSINESS_RULES_IMPLEMENTATION_MATRIX.md`

Kolom:
- Rule ID.
- Deskripsi.
- Database.
- Backend.
- Frontend.
- Test.
- Status Implementasi.
- Catatan.

Gunakan status:
- Not Implemented.
- Partially Implemented.
- Implemented.
- Tested.
- Needs Decision.

Jangan menandai aturan sebagai Implemented hanya karena tabel atau modelnya sudah ada.

## 8. Testing

Buat test scenario untuk kondisi normal, batas, dan kesalahan.

Minimal mencakup:
- Kartu tidak dikenal.
- Kartu nonaktif.
- UID duplikat.
- Tap IN berulang.
- Tap OUT berulang.
- Tap OUT tanpa IN.
- Siswa keluar sementara.
- Siswa tidak kembali.
- Siswa pulang tanpa izin.
- Jadwal pulang cepat.
- Perubahan jadwal setelah kehadiran tercatat.
- Hari libur.
- Terminal offline.
- Event terkirim dua kali.
- Event terlambat.
- Dua terminal mengirim event bersamaan.
- Koreksi data.
- Izin ditolak.
- Permohonan darurat.
- Pengguna tidak memiliki hak akses.

Gunakan automated tests Laravel dan pengujian frontend bila relevan.

## 9. Batasan Penting

- Jangan mengubah data produksi.
- Jangan menjalankan migration destruktif.
- Jangan menghapus tabel atau kolom tanpa analisis dampak.
- Jangan mengarang aturan sekolah.
- Jangan mengubah keputusan bisnis yang sudah disetujui.
- Jangan menganggap anomali RFID sebagai bukti kecurangan.
- Jangan menghapus event mentah.
- Jangan membuat keputusan administratif otomatis tanpa aturan yang disetujui.
- Jangan langsung melanjutkan seluruh implementasi fitur sebelum aturan kritis diselesaikan.

## 10. Urutan Pengerjaan

Kerjakan dengan urutan:

1. Audit seluruh dokumentasi dan kode.
2. Identifikasi aturan yang sudah disetujui.
3. Identifikasi aturan yang belum jelas.
4. Identifikasi konflik antaraturan.
5. Buat BUSINESS_RULES.md.
6. Buat BUSINESS_DECISIONS.md.
7. Buat STATE_TRANSITION_MATRIX.md.
8. Buat ROLE_PERMISSION_MATRIX.md.
9. Buat BUSINESS_RULES_IMPLEMENTATION_MATRIX.md.
10. Buat test scenario.
11. Laporkan keputusan yang membutuhkan persetujuan pengguna.

**Jangan langsung mengimplementasikan kebijakan yang masih berstatus Proposed atau Needs Decision.**

Mulai dari audit dan dokumentasi. Setelah itu berikan ringkasan:
- Aturan yang sudah final.
- Aturan yang masih berupa rekomendasi.
- Konflik yang ditemukan.
- Keputusan yang harus saya tentukan.
- Risiko jika keputusan tersebut belum dibuat.

Pastikan semua dokumen saling konsisten dan siap menjadi dasar implementasi fitur.