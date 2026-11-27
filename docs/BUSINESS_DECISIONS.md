# BUSINESS DECISIONS — Keputusan yang Belum Final

Daftar keputusan yang memerlukan persetujuan pengguna. **Jangan mengubah
status Proposed menjadi Confirmed tanpa persetujuan.** Aturan terkait tidak
boleh diimplementasikan sebelum diputuskan.

---

## Keputusan 26 November 2026 (instruksi final pengguna)

Pengguna menutup brainstorming: keputusan yang sudah disetujui dipakai, sisanya
memakai rekomendasi dan nilainya harus dapat dikonfigurasi. Kolom terakhir
menunjukkan apakah kode sudah mengikuti.

| ID | Keputusan | Kode |
|---|---|---|
| BD-01 | Izin tidak masuk, sakit, pulang awal, keluar sementara diputus wali kelas. Piket hanya mencatat kejadian mendadak, wali kelas mengonfirmasi. Kasus khusus: wali kelas dan admin | Jenis izin pulang awal dan keluar sementara ada. Peran piket sudah tidak memutus. Konfirmasi kejadian mendadak belum |
| BD-02 | Batas mundur pengajuan 3 hari sekolah, bisa diatur | Belum |
| BD-03 | Enam jenis notifikasi orang tua dengan preferensi per orang tua | Baru rekap staf, keputusan permohonan, alpa terkonfirmasi |
| BD-06 | Bukti wajib untuk sakit 2 hari berturut, ambang bisa diatur | Belum |
| BD-07 | Sanggahan orang tua 3 hari sekolah | Belum |
| BD-08 | Registrasi massal lengkap, termasuk batalkan pendaftaran terakhir dan abaikan pembacaan ulang | Selesai di kode, belum dijalankan |
| BD-10 | Jadwal pulang cepat per kelas, tingkat, sekolah | Belum (evaluator masih per sekolah) |
| BD-11 | Koreksi: piket ajukan, wali kelas setujui, setelah hari ditutup admin, lebih dari 7 hari admin senior atau kepala sekolah | Belum |
| BD-12 | Tetap Confirmed. Lihat catatan konflik di bawah | Sesuai BD-12 |

Konflik yang belum diputus: instruksi final menulis persentase kehadiran fisik
sebagai hadir + terlambat dibagi hari efektif **setelah mengecualikan izin dan
sakit yang disetujui**, sedangkan BD-12 yang sudah Confirmed memakai seluruh hari
efektif sebagai penyebut. Kode tetap mengikuti BD-12 sampai pengguna memilih.

---

## BD-01 — Matriks pemutus per jenis permohonan

- **Permasalahan**: Siapa yang berwenang memutus `izin`, `sakit`,
  `dispensasi`, `lainnya`? Saat ini: admin/kepala/wakil untuk semua jenis;
  wali kelas untuk kelas binaan; petugas piket tidak sama sekali.
- **Pilihan**:
  - A. Pertahankan (semua decided oleh pimpinan/wali kelas).
  - B. Petugas piket boleh memutus `dispensasi` saja.
  - C. Matrix per jenis (mis. sakit→wali kelas cukup, dispensasi→koordinator).
- **Rekomendasi**: B atau C — membebaskan pimpinan dari dispensasi rutin.
- **Dampak**: perubahan `RequestPolicy::decide` + test.
- **Risiko jika tidak diputus**: petugas piket mungkin menolak permohonan yang
  seharusnya wewenangnya, atau sebaliknya menekan untuk approve.
- **Penyetuju**: Kepala sekolah / Admin.
- **Status**: Needs Decision.

## BD-02 — Batas waktu & permohonan darurat

- **Permasalahan**: Apakah ada deadline pengajuan izin (mis. H-1)? Bagaimana
  permohonan darurat (sakit mendadak) diproses?
- **Pilihan**: tanpa deadline; deadline dengan jalur darurat (submit langsung
  `submitted` + flag); deadline ketat.
- **Rekomendasi**: tanpa deadline keras, tapi flag `urgent` opsional untuk
  kasus darurat yang diprioritaskan di UI.
- **Dampak**: field tambahan opsional + UI badge.
- **Risiko**: ortu telat mengajukan sakit → status absen salah.
- **Penyetuju**: Kepala sekolah.
- **Status**: Needs Decision.

## BD-03 — Cakupan & prioritas notifikasi

- **Permasalahan**: PRD mensyaratkan ~9 trigger notifikasi; baru 2 jalan
  (request decision, alpa confirmed). Mana yang prioritas? Perlu opt-out?
- **Pilihan**:
  - A. Semua trigger sekaligus.
  - B. Prioritas: tidak ada kehadiran (alpa_menunggu), izin diajukan ke wali,
    perubahan jadwal; skip tap IN/OUT (terlalu ramai).
  - C. B + pengaturan notifikasi per user.
- **Rekomendasi**: B dulu — notifikasi tap IN/OUT per siswa akan membanjiri
  orang tua/wali; batching harian lebih masuk akal.
- **Dampak**: trigger baru di evaluator/schedule + template + preferensi user.
- **Risiko**: ortu tidak tahu anak tidak hadir sampai alpa confirm.
- **Penyetuju**: Kepala sekolah + Admin.
- **Status**: Needs Decision.

## BD-04 — Pemetaan role PRD ke enum implementasi

- **Permasalahan**: PRD menyebut "Guru" dan "Petugas Pengelola RFID"; enum
  punya `wali_kelas`, `petugas_piket`, `koordinator_kegiatan` — tidak ada
  `guru` generik.
- **Pilihan**:
  - A. `guru` = `wali_kelas` + `koordinator_kegiatan` (cukup).
  - B. Tambah role `guru` baru.
- **Rekomendasi**: A — tambah role hanya bila ada kebutuhan guru non-wali
  yang nyata.
- **Dampak**: jika B, migrasi enum + policies + UI role.
- **Risiko**: scope guru tidak jelas → salah akses data kelas.
- **Penyetuju**: Admin.
- **Status**: Needs Decision.

## BD-05 — Hari efektif untuk izin multi-hari & generator alpa

- **Permasalahan**: (a) Izin 3 hari yang mencakup libur tetap menandai hari
  libur sebagai `izin`; (b) `alpa:generate` tidak mengecek hari libur —
  siswa bisa `alpa_menunggu` di hari libur.
- **Pilihan**:
  - A. Izin hanya berlaku hari efektif (skip `tidak_berlaku`/libur);
    generator alpa skip tanggal non-school.
  - B. Biarkan — `alpa_menunggu` di hari libur akan di-resolve manual.
- **Rekomendasi**: A — secara bisnis jelas benar; implementasi kecil
  (cek `schedule_versions` di `applyToAttendance` & `generateForDate`).
- **Dampak**: `AttendanceRequestController::applyToAttendance`,
  `AlpaService::generateForDate`, test baru.
- **Risiko**: laporan kehadilan/libur tercemar status izin/alpa di hari libur.
- **Penyetuju**: Kepala sekolah.
- **Status**: ✅ **Confirmed** (2026-11-25) — Pilihan A disetujui pengguna.
  Diimplementasikan via `SchoolDayService`: hari efektif = dalam tahun ajaran
  aktif DAN tidak ada schedule approved non-school. Berlaku untuk
  `applyToAttendance` (izin multi-hari) dan `AlpaService::generateForDate`.

## BD-06 — Lampiran permohonan

- **Permasalahan**: `attachment_path` ada di skema; wajib atau opsional?
  Untuk jenis apa?
- **Pilihan**: opsional untuk semua; wajib untuk `sakit` >2 hari;
  tidak dipakai sama sekali (Hapus kolom nanti).
- **Rekomendasi**: opsional — upload butuh storage config & validasi file.
- **Dampak**: form upload + validasi + storage.
- **Risiko**: bukti medis tidak tervalidasi; atau ortu tidak bisa upload
  surat dokter untuk sakit panjang.
- **Penyetuju**: Kepala sekolah.
- **Status**: Needs Decision.

## BD-07 — Sanggahan ortu atas keputusan

- **Permasalahan**: Jika permohonan ditolak, ortu tidak punya jalur
  sanggah formal.
- **Pilihan**: tidak perlu (buat permohonan baru); field `appeal` pada
  request_decisions; permohonan ulang dengan referensi.
- **Rekomendasi**: tidak perlu di iterasi ini — ortu bisa mengajukan ulang
  dengan alasan lebih lengkap; catat sebagai backlog.
- **Status**: Needs Decision (prioritas rendah).

## BD-08 — Fitur registrasi massal yang belum ada

- **Permasalahan**: PRD mensyaratkan antrean siswa tanpa kartu, auto-next,
  pause/resume/skip/cancel, indikator progres. Implementasi saat ini:
  scan per siswa terpilih + finish.
- **Pilihan**:
  - A. Lengkapi sekarang (auto-next + pause/resume/skip).
  - B. Cukup scan manual per siswa (saat ini) — petugas memilih siswa.
- **Rekomendasi**: A untuk operasional massal yang cepat, tapi B dapat
  diterima untuk pilot jika waktu terbatas.
- **Dampak**: endpoint pause/resume/skip + state sesi + UI antrean.
- **Risiko**: registrasi 300 siswa memakan waktu lama tanpa auto-next.
- **Penyetuju**: Petugas piket + Admin.
- **Status**: Needs Decision.

## BD-09 — Terminal IN vs OUT: apakah scan IN di terminal OUT valid?

- **Permasalahan**: Evaluator memperlakukan scan berdasarkan `terminal.type`;
  tidak ada larangan lintas terminal. Jika siswa scan kartu di terminal OUT
  untuk "masuk", itu dicatat sebagai movement `out` → bisa `perlu_konfirmasi`.
- **Pilihan**:
  - A. Biarkan — terminal type menentukan arah; anomali → perlu_konfirmasi.
  - B. Terminal IN hanya menerima scan "masuk" dan sebaliknya (warning).
- **Rekomendasi**: A — fleksibilitas untuk gerbang yang berubah arah;
  anomali sudah ditangkap review flow.
- **Status**: Needs Decision (prioritas rendah).

## BD-10 — Cakupan jadwal per kelas/tingkat

- **Permasalahan**: `academic_schedules.scope_type`/`scope_id` ada di skema
  tapi selalu `school`. PRD early dismissal mensyaratkan cakupan sekolah/
  tingkat/kelas.
- **Pilihan**: implement scope class/grade + evaluator memilih versi paling
  spesifik; atau tetap school-wide.
- **Rekomendasi**: implement scope — kolom sudah ada, tapi menambah
  kompleksitas resolusi jadwal (perlu join kelas siswa).
- **Dampak**: `AttendanceEvaluator::scheduleFor` harus resolve per siswa;
  UI filter scope.
- **Risiko**: pulang awal per kelas tidak bisa dilakukan → semua sekolah
  harus pulang awal.
- **Penyetuju**: Kepala sekolah.
- **Status**: Needs Decision.

## BD-11 — Approval dua tahap untuk koreksi manual

- **Permasalahan**: `manual_adjustments` punya `requested_by`/`approved_by`
  tetapi review resolve langsung apply tanpa approval kedua.
- **Pilihan**:
  - A. Pertahankan single-step (admin/petugas resolve langsung).
  - B. Dua tahap: petugas request → admin approve.
- **Rekomendasi**: A untuk sekarang — volume koreksi rendah; audit log sudah
  mencatat actor. Tinjau ulang bila ada insiden.
- **Status**: Needs Decision (prioritas rendah).

## BD-12 — Definisi resmi persentase kehadiran

- **Permasalahan**: Formula laporan belum ada (Phase 9 pending).
- **Pilihan**:
  - A. Hadir fisik = `hadir + terlambat`; penyebut = hari efektif
    (non-libur); `izin/sakit/alpa` sebagai kategori terpisah (bukan hadir).
  - B. Izin/sakit dihitung "terhitung hadir" untuk persentase.
- **Rekomendasi**: A — sesuai PRD "jangan anggap izin/sakit sebagai
  kehadiran fisik".
- **Dampak**: definisi ini menentukan semua laporan Phase 9.
- **Penyetuju**: Kepala sekolah.
- **Status**: ✅ **Confirmed** (2026-11-25) — Formula disetujui:
  `(hadir + terlambat) / hari_efektif × 100`. Izin/sakit/alpa kategori
  terpisah, tidak dihitung ganda. Diimplementasikan di `ReportController`
  + halaman Rekap.

---

## Prioritas untuk Diputuskan Duluan

| Prioritas | ID | Alasan |
|---|---|---|
| ~~1~~ | ~~BD-05~~ | ✅ Confirmed & diimplementasikan |
| ~~2~~ | ~~BD-12~~ | ✅ Confirmed & diimplementasikan (Phase 9) |
| 3 | BD-03 | Menentukan scope notifikasi (ditunda — tetap 2 trigger) |
| 4 | BD-01 | Menentukan siapa decide permohonan apa (ditunda — status quo) |
| 5 | BD-10 | Menentukan kompleksitas jadwal & evaluator |
