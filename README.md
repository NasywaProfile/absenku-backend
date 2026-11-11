# Absenku Backend — Dokumentasi REST API & Skema Database (Tugas Akhir)

Backend REST API untuk sistem presensi dan manajemen operasional kerja **Absenku**, dibangun menggunakan **Laravel 11**, **Laravel Sanctum**, dan **MySQL** (dikelola via XAMPP phpMyAdmin).

---

## 🗄️ Kamus Data & Skema Database (`absenku_db`)

Database ini dirancang dengan penamaan tabel dan atribut berbahasa Indonesia yang baku, rapi, dan terstandarisasi untuk keperluan presentasi dan laporan **Tugas Akhir (TA)**.

### 1. Tabel: `pengguna`
Menyimpan data otentikasi, identitas kepegawaian, peran (RBAC), data finansial penggajian, dan status akun karyawan.
* **`id`** (`VARCHAR(50)`, Primary Key) : Identifikasi unik pengguna (misal: `usr_super_admin`, `usr_1730...`).
* **`nama`** (`VARCHAR(255)`) : Nama lengkap karyawan / administrator.
* **`email`** (`VARCHAR(255)`, Unique) : Alamat email aktif untuk login sistem.
* **`password`** (`VARCHAR(255)`) : Hash kata sandi terenkripsi (Bcrypt / Argon2).
* **`peran`** (`VARCHAR(30)`) : Peran akses sistem (`SUPER_ADMIN`, `ADMIN`, `EMPLOYEE`).
* **`status`** (`VARCHAR(30)`) : Status akun (`ACTIVE`, `PENDING_APPROVAL`, `REJECTED`).
* **`tipe_kepegawaian`** (`VARCHAR(50)`) : Status kerja (`PERMANENT`, `CONTRACT`, `INTERN`, `FREELANCE`).
* **`divisi`** (`VARCHAR(100)`) : Nama departemen / divisi penempatan.
* **`jabatan`** (`VARCHAR(100)`) : Posisi / jabatan karyawan.
* **`foto_profil`** (`TEXT`) : URL atau data gambar avatar karyawan.
* **`nomor_telepon`** (`VARCHAR(50)`) : Nomor WhatsApp aktif untuk kontak dan notifikasi.
* **`tanggal_bergabung`** (`VARCHAR(30)`) : Tanggal mulai bekerja.
* **`sisa_cuti`** (`INT`) : Saldo kuota cuti tahunan (default: 12 hari).
* **`nama_bank`** (`VARCHAR(100)`) : Nama bank payroll karyawan (misal: BCA, Mandiri, BRI).
* **`nomor_rekening`** (`VARCHAR(100)`) : Nomor rekening pembayaran gaji.
* **`nama_pemilik_rekening`** (`VARCHAR(150)`) : Nama sesuai buku tabungan karyawan.
* **`nama_kontak_darurat`** (`VARCHAR(150)`) : Nama wali / kerabat darurat.
* **`nomor_kontak_darurat`** (`VARCHAR(50)`) : Nomor telepon kontak darurat.
* **`alamat`** (`TEXT`) : Alamat domisili tempat tinggal.
* **`waktu_daftar`** (`VARCHAR(50)`) : Waktu pendaftaran akun dibuat.
* **`alasan_penolakan`** (`TEXT`, nullable) : Catatan penolakan jika verifikasi akun ditolak admin.
* **`profil_lengkap`** (`BOOLEAN`) : Status kelengkapan data diri karyawan (true/false).
* **`created_at`, `updated_at`** (`TIMESTAMP`) : Waktu pencatatan data.

---

### 2. Tabel: `konfigurasi_kantor`
Menyimpan konfigurasi geofence GPS kantor pusat/cabang dan aturan jam kerja.
* **`id`** (`BIGINT UNSIGNED`, Primary Key) : Nomor identifikasi pengaturan.
* **`nama_kantor`** (`VARCHAR(255)`) : Nama lokasi gedung kantor (misal: IndigoSpace Malang).
* **`alamat_kantor`** (`TEXT`) : Alamat fisik lengkap kantor.
* **`latitude`** (`DECIMAL(10,8)`) : Titik koordinat garis lintang GPS kantor.
* **`longitude`** (`DECIMAL(11,8)`) : Titik koordinat garis bujur GPS kantor.
* **`radius_geofence_meter`** (`INT`) : Batas radius presensi yang diizinkan (default: 100 meter).
* **`jam_masuk`** (`VARCHAR(10)`) : Jam masuk standar kantor (misal: 08:00).
* **`jam_pulang`** (`VARCHAR(10)`) : Jam kepulangan kantor (misal: 17:00).
* **`toleransi_keterlambatan_menit`** (`INT`) : Batas menit dispensasi sebelum terhitung terlambat.
* **`status_geofence_aktif`** (`BOOLEAN`) : Status pengaktifan validasi radius GPS (true/false).

---

### 3. Tabel: `presensi`
Mencatat riwayat kehadiran harian karyawan secara otomatis berdasarkan geolokasi GPS dan jam masuk.
* **`id`** (`VARCHAR(50)`, Primary Key) : Nomor unik transaksi presensi (misal: `att_1730...`).
* **`pengguna_id`** (`VARCHAR(50)`, Foreign Key ke `pengguna.id`) : Relasi akun karyawan.
* **`nama_karyawan`** (`VARCHAR(255)`) : Snapshot nama karyawan saat presensi.
* **`foto_karyawan`** (`TEXT`, nullable) : Foto karyawan.
* **`divisi`** (`VARCHAR(100)`, nullable) : Divisi karyawan.
* **`tipe_kepegawaian`** (`VARCHAR(50)`, nullable) : Tipe kontrak saat presensi dilakukan.
* **`tanggal`** (`DATE`) : Tanggal presensi (format: YYYY-MM-DD).
* **`jam_masuk`** (`VARCHAR(20)`) : Waktu tepat presensi masuk dicatat (HH:mm).
* **`latitude`** (`DECIMAL(10,8)`) : Titik koordinat GPS lintang saat karyawan presensi.
* **`longitude`** (`DECIMAL(11,8)`) : Titik koordinat GPS bujur saat karyawan presensi.
* **`jarak_meter`** (`INT`) : Jarak hasil kalkulasi formula Haversine ke titik pusat kantor.
* **`status_lokasi`** (`VARCHAR(50)`) : Status lokasi (`IN_OFFICE`, `OUT_OF_RANGE`, `WFH_APPROVED`, `DINAS_APPROVED`).
* **`status_kehadiran`** (`VARCHAR(50)`) : Ketepatan waktu (`ON_TIME`, `LATE`).
* **`menit_keterlambatan`** (`INT`) : Total menit keterlambatan masuk kantor.
* **`mode_kerja`** (`VARCHAR(30)`) : Mode operasional (`WFO`, `WFH`, `DINAS`).
* **`catatan`** (`TEXT`, nullable) : Keterangan tambahan presensi.
* **`status_sinkronisasi`** (`VARCHAR(30)`) : Status data presensi (`SYNCED`).

---

### 4. Tabel: `pengajuan_izin`
Mengelola seluruh permohonan ketidakhadiran karyawan (cuti tahunan, sakit, WFH, dinas luar).
* **`id`** (`VARCHAR(50)`, Primary Key) : Nomor permohonan izin (misal: `lve_1730...`).
* **`pengguna_id`** (`VARCHAR(50)`, Foreign Key ke `pengguna.id`) : Akun pemohon izin.
* **`nama_karyawan`** (`VARCHAR(255)`) : Nama lengkap pemohon.
* **`foto_karyawan`** (`TEXT`, nullable) : Foto pemohon.
* **`divisi`** (`VARCHAR(100)`, nullable) : Divisi pemohon.
* **`kategori_izin`** (`VARCHAR(50)`) : Kategori pengajuan (`LEAVE`, `WFH`, `SICK`, `BUSINESS_TRIP`).
* **`tanggal_mulai`** (`DATE`) : Tanggal awal izin.
* **`tanggal_selesai`** (`DATE`) : Tanggal akhir izin.
* **`jumlah_hari`** (`INT`) : Durasi hari kerja yang diajukan.
* **`alasan`** (`TEXT`) : Alasan/urgensi permohonan izin.
* **`nama_lampiran`** (`VARCHAR(255)`, nullable) : Nama berkas bukti (misal: surat dokter).
* **`data_lampiran`** (`LONGTEXT`, nullable) : Data dokumen berkas pendukung.
* **`tipe_lampiran`** (`VARCHAR(50)`, nullable) : Format MIME berkas (PDF / Gambar).
* **`status_pengajuan`** (`VARCHAR(50)`) : Status verifikasi (`PENDING`, `APPROVED`, `REJECTED`).
* **`waktu_pengajuan`** (`VARCHAR(50)`) : Waktu formulir dikirimkan.
* **`disetujui_oleh`** (`VARCHAR(150)`, nullable) : Nama admin yang menyetujui / menolak.
* **`waktu_persetujuan`** (`VARCHAR(50)`, nullable) : Waktu verifikasi diputuskan.
* **`alasan_penolakan`** (`TEXT`, nullable) : Alasan jika permohonan ditolak.

---

### 5. Tabel: `logbook_harian`
Mencatat laporan hasil kerja harian karyawan untuk pemantauan akuntabilitas.
* **`id`** (`BIGINT UNSIGNED`, Primary Key) : ID logbook.
* **`pengguna_id`** (`VARCHAR(50)`, Foreign Key ke `pengguna.id`) : Akun pembuat log.
* **`nama_karyawan`** (`VARCHAR(255)`) : Nama karyawan.
* **`tanggal`** (`DATE`) : Tanggal pelaporan tugas.
* **`daftar_tugas`** (`JSON`) : Kumpulan daftar aktivitas pekerjaan yang dikerjakan.
* **`catatan`** (`TEXT`, nullable) : Catatan kendala atau ringkasan pekerjaan.

---

### 6. Tabel: `tugas_proyek`
Mengelola kartu alur kerja pada papan Kanban kolaboratif tim.
* **`id`** (`VARCHAR(50)`, Primary Key) : ID kartu tugas (misal: `tsk_1730...`).
* **`judul_tugas`** (`VARCHAR(255)`) : Judul pekerjaan yang harus diselesaikan.
* **`deskripsi`** (`TEXT`, nullable) : Detail rincian tugas.
* **`status_tugas`** (`VARCHAR(50)`) : Kolom papan (`TODO`, `IN_PROGRESS`, `DONE`, `REVISION`).
* **`prioritas`** (`VARCHAR(30)`) : Tingkat urgensi (`LOW`, `MEDIUM`, `HIGH`, `URGENT`).
* **`ditugaskan_ke`** (`VARCHAR(50)`, nullable) : User ID penanggung jawab utama.
* **`nama_petugas`** (`VARCHAR(255)`, nullable) : Nama penanggung jawab.
* **`foto_petugas`** (`TEXT`, nullable) : Foto penanggung jawab.
* **`daftar_anggota`** (`JSON`, nullable) : Daftar rekan tim yang didelegasikan.
* **`dibuat_oleh`** (`VARCHAR(100)`, nullable) : Pembuat tugas (Admin / Lead).
* **`tenggat_waktu`** (`DATE`, nullable) : Batas akhir pengerjaan.
* **`lampiran`** (`JSON`, nullable) : Lampiran dokumen kerja.

---

### 7. Tabel: `topik_diskusi`
Mengelola topik bahasan diskusi terstruktur antar karyawan dan tim.
* **`id`** (`VARCHAR(50)`, Primary Key) : ID topik (misal: `tpc_1730...`).
* **`judul_topik`** (`VARCHAR(255)`) : Judul subjek diskusi.
* **`status_topik`** (`VARCHAR(50)`) : Status diskusi (`NEED_COMMENTS`, `IN_PROGRESS`, `CLOSED`).
* **`id_anggota_ditandai`** (`JSON`, nullable) : Daftar User ID yang dimention/ditandai.
* **`berkas`** (`JSON`, nullable) : Kumpulan dokumen terlampir.
* **`tautan`** (`JSON`, nullable) : Tautan referensi luar.
* **`gambar`** (`JSON`, nullable) : Galeri gambar terlampir.
* **`catatan_singkat`** (`TEXT`, nullable) : Ringkasan pengantar topik.
* **`catatan_lengkap`** (`LONGTEXT`, nullable) : Deskripsi menyeluruh materi diskusi.
* **`dibuat_oleh_id`** (`VARCHAR(50)`) : User ID pembuat topik.
* **`dibuat_oleh_nama`** (`VARCHAR(255)`) : Nama pembuat topik.
* **`dibuat_oleh_foto`** (`TEXT`, nullable) : Foto profil pembuat topik.

---

### 8. Tabel: `pesan_diskusi`
Mencatat pesan percakapan dan balasan interaktif di dalam suatu topik atau kanal umum.
* **`id`** (`VARCHAR(50)`, Primary Key) : ID pesan (misal: `msg_1730...`).
* **`topik_id`** (`VARCHAR(50)`, nullable, FK ke `topik_diskusi.id`) : Relasi topik diskusi.
* **`pengguna_id`** (`VARCHAR(50)`, FK ke `pengguna.id`) : Pengirim pesan.
* **`nama_pengirim`** (`VARCHAR(255)`) : Nama lengkap pengirim.
* **`foto_pengirim`** (`TEXT`, nullable) : Foto profil pengirim.
* **`peran_pengirim`** (`VARCHAR(30)`) : Peran pengirim.
* **`tipe_kepegawaian`** (`VARCHAR(50)`) : Tipe kepegawaian pengirim.
* **`isi_pesan`** (`TEXT`) : Konten teks percakapan.
* **`waktu_kirim`** (`VARCHAR(30)`) : Waktu pesan dikirimkan (HH:mm).
* **`kanal`** (`VARCHAR(50)`) : Nama kanal percakapan (default: `general`).
* **`lampiran`** (`JSON`, nullable) : Lampiran berkas interaktif pada pesan.

---

### 9. Tabel: `personal_access_tokens`
Tabel standar resmi dari **Laravel Sanctum** untuk pengelolaan token otentikasi Bearer API yang aman dan terenkripsi.

---

## ⚡ Cara Menjalankan Backend & Praktik Produksi

### 1. Lingkungan Lokal (Development)
1. **Jalankan Apache & MySQL di XAMPP Control Panel**.
2. Masuk ke direktori backend:
   ```bash
   cd D:\Project-Indiekraf-Internship\absenku-backend
   ```
3. Jalankan migrasi dan setup akun Super Admin (via `.env` atau perintah Artisan):
   ```bash
   php artisan migrate
   php artisan absenku:set-superadmin
   ```
4. Jalankan Server API:
   ```bash
   php artisan serve --port=8000
   ```
API siap digunakan pada: `http://127.0.0.1:8000/api`

### 2. Aturan Wajib Server Produksi
* **JANGAN PERNAH** menjalankan `php artisan migrate:fresh` pada server produksi (diblokir otomatis saat `APP_ENV=production`). Gunakan `php artisan migrate --force` hanya setelah membuat backup.
* **Setel `.env` produksi:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain-anda`, `APP_FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, dan `CORS_ALLOWED_ORIGINS=https://domain-anda`.
* **Aktifkan Backup Database Otomatis:**
  Jalankan manual: `php artisan db:backup` (tersimpan terkompresi di `storage/app/backups/` dengan retensi 14 hari), dan pasang Laravel scheduler cron di server:
  ```cron
  * * * * * cd /var/www/absenku-backend && php artisan schedule:run >> /dev/null 2>&1
  ```
