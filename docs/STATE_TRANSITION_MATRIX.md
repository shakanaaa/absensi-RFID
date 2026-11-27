# STATE TRANSITION MATRIX — Kehadiran & Pergerakan

Sumber: `AttendanceEvaluator` rule_version **v1.1**. Status penilaian:
**Valid** = transisi normal; **Perlu Konfirmasi** = dicatat, menunggu review;
**Idempotent** = diakui tanpa perubahan state.

Kolom "Dampak laporan": H=hadir fisik, T=terlambat, A=alpa, I=izin/sakit/
dispensasi, NB=tidak berlaku, -=tidak dihitung.

---

## 1. Attendance Status (`attendance_status`)

| # | Status awal | Event pemicu | Syarat | Status akhir | Valid? | Penanganan jika tidak valid | Dampak laporan | Notifikasi | Test |
|---|---|---|---|---|---|---|---|---|---|
| S1 | `belum_masuk` | Scan IN pertama | Hari sekolah, sesuai jadwal | `hadir` | Ya | — | H | — | DeviceApiTest |
| S2 | `belum_masuk` | Scan IN pertama | `event_time > start_time` | `terlambat` | Ya | — | T | — | DeviceApiTest |
| S3 | `belum_masuk` | Scan IN | Hari libur (`type` holiday/libur/tidak_berlaku) | `tidak_berlaku` | Ya | Tanpa movement | NB | — | AttendanceEdgeCaseTest |
| S4 | `belum_masuk` | Generator `alpa:generate` | Siswa aktif, tanpa aktivitas tanggal tsb | `alpa_menunggu` | Ya | Idempotent; tidak menimpa status lain | A? | — | RequestAndAlpaTest |
| S5 | `alpa_menunggu` | Verify wali kelas | Siswa di kelas binaan wali | tetap `alpa_menunggu` + `alpa_verified_*` | Ya | 422 bila bukan alpa_menunggu | — | — | RequestAndAlpaTest |
| S6 | `alpa_menunggu` | Confirm admin | `alpa_verified_at` terisi | `alpa` | Ya | 422 bila belum verified | A | NOTIF alpa.confirmed | RequestAndAlpaTest, NotificationTest |
| S7 | `belum_masuk/hadir/terlambat` | Permohonan approved (izin/sakit/dispensasi) | Tanggal dlm rentang permohonan | `izin`/`sakit`/`dispensasi` | Ya | Tidak menimpa status final | I | NOTIF request.* | RequestAndAlpaTest |
| S8 | `*` (non-final) | Scan RFID | Status ∈ PROTECTED (izin/sakit/alpa*/dispensasi/tidak_berlaku) | tetap | Ya | Movement tetap dicatat | sesuai status | — | AttendanceEdgeCaseTest |
| S9 | `alpa`/`tidak_berlaku` | Scan RFID | — | tetap | Ya | Movement dicatat; status tidak ditimpa | A/NB | — | AttendanceEdgeCaseTest |

Catatan: `alpa` final TIDAK pernah dibuat otomatis — hanya via S6.

---

## 2. Movement State (`movement_state`)

Terminal `in`:

| # | State awal | Scan | Syarat | State akhir | Movement | Outcome | Valid? |
|---|---|---|---|---|---|---|---|
| M1 | `belum_di_sekolah` | IN | hari sekolah | `di_sekolah` | `masuk` | `hadir`/`terlambat` | Ya |
| M2 | `keluar_sementara` | IN | — | `di_sekolah` | `kembali` | `kembali` | Ya |
| M3 | `di_sekolah` | IN | — | tetap | — | `sudah_di_sekolah` | Ya (idempotent) |
| M4 | `sudah_pulang` | IN | — | `perlu_konfirmasi` | `perlu_konfirmasi` | `perlu_konfirmasi` | Perlu review |
| M5 | `perlu_konfirmasi` | IN | — | `perlu_konfirmasi` | `perlu_konfirmasi` | `perlu_konfirmasi` | Perlu review |

Terminal `out`:

| # | State awal | Scan | Syarat | State akhir | Movement | Outcome | Valid? |
|---|---|---|---|---|---|---|---|
| M6 | `di_sekolah` | OUT | `event_time < end_time` | `keluar_sementara` | `keluar_sementara` | `keluar_sementara` | Ya |
| M7 | `di_sekolah` | OUT | `event_time >= end_time` / tanpa jadwal | `sudah_pulang` | `pulang` | `pulang` | Ya |
| M8 | `belum_di_sekolah` | OUT | — | `perlu_konfirmasi` | `perlu_konfirmasi` | `perlu_konfirmasi` | Perlu review |
| M9 | `keluar_sementara` | OUT | — | tetap | — | `sudah_keluar` | Ya (idempotent) |
| M10 | `sudah_pulang` | OUT | — | tetap | — | `sudah_pulang` | Ya (idempotent) |
| M11 | `perlu_konfirmasi` | OUT | — | `perlu_konfirmasi` | `perlu_konfirmasi` | `perlu_konfirmasi` | Perlu review |

Aturan lintas-state:

| # | Kondisi | Penanganan | Test |
|---|---|---|---|
| M12 | Scan ≤5 detik dari gerakan terakhir (event_time) | `ignored_duplicate_scan`, tanpa movement | AttendanceEdgeCaseTest |
| M13 | Hari libur | outcome `tidak_berlaku`, tanpa transisi | AttendanceEdgeCaseTest |
| M14 | Kartu tak dikenal (`unknown_card`) | outcome `unknown_card`, tanpa sesi | DeviceApiTest |
| M15 | Kartu non-aktif/siswa non-aktif | outcome sesuai resolusi gagal, tanpa sesi | DeviceApiTest |
| M16 | `perlu_konfirmasi` di-resolve admin/petugas | state+status aktual + `manual_adjustments` + audit | AttendanceEdgeCaseTest |
| M17 | Event duplikat (`event_id` sama) | ack `duplicate`, tidak dievaluasi ulang | DeviceApiTest |
| M18 | `event_time` drift >24 jam | tetap dievaluasi, `time_quality=skewed` | AttendanceEdgeCaseTest |
| M19 | Kartu dikenal tapi status non-assignable | outcome `inactive_card`, tanpa sesi | AttendanceEdgeCaseTest |

---

## 3. Daftar kosong / tidak ter-transisi (explicit)

Transisi berikut **tidak valid** dan tidak menghasilkan state change:

- IN dari `perlu_konfirmasi` tanpa review → tetap `perlu_konfirmasi` (bukan langsung valid).
- OUT dari `belum_di_sekolah` → tidak pernah dianggap `keluar_sementara`/`sudah_pulang`.
- Status absen `alpa` tidak pernah kembali ke `hadir` via scan (harus koreksi manual).
- Siswa tanpa tap IN tidak pernah mendapat waktu OUT fiktif.
