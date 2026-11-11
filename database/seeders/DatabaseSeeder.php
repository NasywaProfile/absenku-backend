<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Konfigurasi Kantor Pusat (hanya inisialisasi jika tabel masih kosong)
        if (DB::table('konfigurasi_kantor')->count() === 0) {
            DB::table('konfigurasi_kantor')->insert([
                'id' => 1,
                'nama_kantor' => 'IndigoSpace Malang (Kantor Pusat)',
                'alamat_kantor' => 'Jl. Simpang Borobudur No. 1, Mojolangu, Kec. Lowokwaru, Kota Malang, Jawa Timur 65142',
                'latitude' => -7.94390000,
                'longitude' => 112.63220000,
                'radius_geofence_meter' => 100,
                'jam_masuk' => '08:00',
                'jam_pulang' => '17:00',
                'toleransi_keterlambatan_menit' => 0,
                'status_geofence_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Data Pengguna Super Admin Awal (HANYA dibuat jika belum ada; tidak pernah menimpa akun existing)
        $hasSuperAdmin = DB::table('pengguna')
            ->where('id', 'usr_super_admin')
            ->orWhere('peran', 'SUPER_ADMIN')
            ->exists();

        if (! $hasSuperAdmin) {
            $saEmail = strtolower(trim((string) config('absenku.seed_super_admin_email')));
            $saPassword = (string) config('absenku.seed_super_admin_password');
            $saName = trim((string) (config('absenku.seed_super_admin_name') ?: 'Super Admin'));

            if ($saEmail === '' || $saPassword === '') {
                if (app()->isProduction()) {
                    $this->command?->warn(
                        'Super Admin belum dibuat karena SEED_SUPER_ADMIN_EMAIL / SEED_SUPER_ADMIN_PASSWORD belum disetel. Jalankan: php artisan absenku:set-superadmin'
                    );
                    return;
                }

                $saEmail = $saEmail !== '' ? $saEmail : 'admin@absenku.local';
                $saPassword = $saPassword !== '' ? $saPassword : Str::password(20);
                $this->command?->warn("Super Admin lokal dibuat otomatis -> Email: {$saEmail} | Password acak: {$saPassword}");
            }

            DB::table('pengguna')->insert([
                'id' => 'usr_super_admin',
                'nama' => $saName,
                'email' => $saEmail,
                'password' => Hash::make($saPassword),
                'peran' => 'SUPER_ADMIN',
                'status' => 'ACTIVE',
                'tipe_kepegawaian' => 'PERMANENT',
                'divisi' => 'Direksi Eksekutif',
                'jabatan' => 'Super Administrator',
                'foto_profil' => '',
                'nomor_telepon' => '',
                'tanggal_bergabung' => date('Y-m-d'),
                'sisa_cuti' => 24,
                'waktu_daftar' => date('Y-m-d H:i'),
                'profil_lengkap' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Data dummy dilarang keras di-seed pada environment produksi
        if (app()->isProduction() || ! config('absenku.seed_dummy_data', false)) {
            return;
        }

        $dummyPasswordHash = Hash::make((string) (config('absenku.seed_dummy_password') ?: Str::password(16)));

        // Data Karyawan Dummy (Hanya dijalankan di non-produksi jika SEED_DUMMY_DATA=true)
        $users = [
            [
                'id' => 'usr_emp_01',
                'nama' => 'Budi Santoso',
                'email' => 'budi.santoso@absenku.id',
                'password' => $dummyPasswordHash,
                'peran' => 'EMPLOYEE',
                'status' => 'ACTIVE',
                'tipe_kepegawaian' => 'PERMANENT',
                'divisi' => 'Teknologi & IT',
                'jabatan' => 'Lead Fullstack Developer',
                'foto_profil' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=256&q=80',
                'nomor_telepon' => '081234567890',
                'tanggal_bergabung' => '2024-02-01',
                'sisa_cuti' => 12,
                'nama_bank' => 'Bank Mandiri',
                'nomor_rekening' => '1440019283741',
                'nama_pemilik_rekening' => 'Budi Santoso',
                'nama_kontak_darurat' => 'Ratna Santoso (Istri)',
                'nomor_kontak_darurat' => '081234567899',
                'alamat' => 'Perumahan Griya Shanta Blok E No. 12, Lowokwaru, Kota Malang',
                'waktu_daftar' => '2024-02-01 08:00',
                'profil_lengkap' => true,
            ],
            [
                'id' => 'usr_emp_02',
                'nama' => 'Siti Rahmawati',
                'email' => 'siti.rahmawati@absenku.id',
                'password' => $dummyPasswordHash,
                'peran' => 'EMPLOYEE',
                'status' => 'ACTIVE',
                'tipe_kepegawaian' => 'PERMANENT',
                'divisi' => 'Desain & Produk',
                'jabatan' => 'Lead UI/UX Designer',
                'foto_profil' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=256&q=80',
                'nomor_telepon' => '082198765432',
                'tanggal_bergabung' => '2024-03-15',
                'sisa_cuti' => 10,
                'nama_bank' => 'BCA (Bank Central Asia)',
                'nomor_rekening' => '3150821941',
                'nama_pemilik_rekening' => 'Siti Rahmawati',
                'nama_kontak_darurat' => 'H. Rahmawati (Ayah)',
                'nomor_kontak_darurat' => '082198765400',
                'alamat' => 'Jl. Soekarno Hatta No. 45, Mojolangu, Kota Malang',
                'waktu_daftar' => '2024-03-15 08:30',
                'profil_lengkap' => true,
            ],
            [
                'id' => 'usr_emp_03',
                'nama' => 'Ahmad Fauzi',
                'email' => 'ahmad.fauzi@absenku.id',
                'password' => $dummyPasswordHash,
                'peran' => 'EMPLOYEE',
                'status' => 'ACTIVE',
                'tipe_kepegawaian' => 'CONTRACT',
                'divisi' => 'Teknologi & IT',
                'jabatan' => 'Mobile App Developer',
                'foto_profil' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=256&q=80',
                'nomor_telepon' => '081377889900',
                'tanggal_bergabung' => '2024-06-01',
                'sisa_cuti' => 8,
                'nama_bank' => 'BNI (Bank Negara Indonesia)',
                'nomor_rekening' => '0459821430',
                'nama_pemilik_rekening' => 'Ahmad Fauzi',
                'nama_kontak_darurat' => 'Dewi Fauzi (Ibu)',
                'nomor_kontak_darurat' => '081377889911',
                'alamat' => 'Jl. MT Haryono Gg. XIX No. 8, Dinoyo, Kota Malang',
                'waktu_daftar' => '2024-06-01 09:00',
                'profil_lengkap' => true,
            ],
            [
                'id' => 'usr_emp_04',
                'nama' => 'Dewi Lestari',
                'email' => 'dewi.lestari@absenku.id',
                'password' => $dummyPasswordHash,
                'peran' => 'EMPLOYEE',
                'status' => 'ACTIVE',
                'tipe_kepegawaian' => 'PERMANENT',
                'divisi' => 'HR & Operasional',
                'jabatan' => 'HR Specialist & GA',
                'foto_profil' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=256&q=80',
                'nomor_telepon' => '085712345678',
                'tanggal_bergabung' => '2024-01-10',
                'sisa_cuti' => 14,
                'nama_bank' => 'BRI (Bank Rakyat Indonesia)',
                'nomor_rekening' => '008801029384501',
                'nama_pemilik_rekening' => 'Dewi Lestari',
                'nama_kontak_darurat' => 'Bambang Lestari (Suami)',
                'nomor_kontak_darurat' => '085712345600',
                'alamat' => 'Jl. Candi Mendut No. 22, Mojolangu, Kota Malang',
                'waktu_daftar' => '2024-01-10 08:00',
                'profil_lengkap' => true,
            ],
            [
                'id' => 'usr_emp_05',
                'nama' => 'Rizky Pratama',
                'email' => 'rizky.pratama@absenku.id',
                'password' => $dummyPasswordHash,
                'peran' => 'EMPLOYEE',
                'status' => 'ACTIVE',
                'tipe_kepegawaian' => 'PERMANENT',
                'divisi' => 'Pemasaran & Media',
                'jabatan' => 'Digital Marketing & Growth',
                'foto_profil' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=256&q=80',
                'nomor_telepon' => '081299112233',
                'tanggal_bergabung' => '2024-04-01',
                'sisa_cuti' => 11,
                'nama_bank' => 'BCA (Bank Central Asia)',
                'nomor_rekening' => '8291049582',
                'nama_pemilik_rekening' => 'Rizky Pratama',
                'nama_kontak_darurat' => 'Irawan Pratama (Kakak)',
                'nomor_kontak_darurat' => '081299112299',
                'alamat' => 'Jl. Bendungan Sigura-gura No. 18, Sumbersari, Kota Malang',
                'waktu_daftar' => '2024-04-01 08:30',
                'profil_lengkap' => true,
            ],
            [
                'id' => 'usr_emp_06',
                'nama' => 'Nadia Putri',
                'email' => 'nadia.putri@absenku.id',
                'password' => $dummyPasswordHash,
                'peran' => 'EMPLOYEE',
                'status' => 'ACTIVE',
                'tipe_kepegawaian' => 'INTERN',
                'divisi' => 'Pemasaran & Media',
                'jabatan' => 'Social Media & Content Writer Intern',
                'foto_profil' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=256&q=80',
                'nomor_telepon' => '089611223344',
                'tanggal_bergabung' => '2026-09-01',
                'sisa_cuti' => 5,
                'nama_bank' => 'Bank Jago',
                'nomor_rekening' => '1029384756',
                'nama_pemilik_rekening' => 'Nadia Putri',
                'nama_kontak_darurat' => 'Endang Putri (Ibu)',
                'nomor_kontak_darurat' => '089611223300',
                'alamat' => 'Jl. Veteran No. 10, Ketawanggede, Kota Malang',
                'waktu_daftar' => '2026-09-01 08:00',
                'profil_lengkap' => true,
            ],
            [
                'id' => 'usr_emp_07',
                'nama' => 'Dimas Wahyudi',
                'email' => 'dimas.wahyudi@absenku.id',
                'password' => $dummyPasswordHash,
                'peran' => 'EMPLOYEE',
                'status' => 'ACTIVE',
                'tipe_kepegawaian' => 'INTERN',
                'divisi' => 'Teknologi & IT',
                'jabatan' => 'QA Tester & Automation Intern',
                'foto_profil' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&w=256&q=80',
                'nomor_telepon' => '087855667788',
                'tanggal_bergabung' => '2026-09-01',
                'sisa_cuti' => 5,
                'nama_bank' => 'BCA (Bank Central Asia)',
                'nomor_rekening' => '7190284712',
                'nama_pemilik_rekening' => 'Dimas Wahyudi',
                'nama_kontak_darurat' => 'Wahyudi (Ayah)',
                'nomor_kontak_darurat' => '087855667700',
                'alamat' => 'Jl. Kalpataru No. 34, Jatimulyo, Kota Malang',
                'waktu_daftar' => '2026-09-01 08:00',
                'profil_lengkap' => true,
            ],
        ];

        foreach ($users as $user) {
            DB::table('pengguna')->updateOrInsert(
                ['email' => $user['email']],
                array_merge($user, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // 3. Data Presensi Realistis (Hari Ini & Hari Kemarin)
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        $attendance = [
            // Presensi Hari Ini
            [
                'id' => 'att_today_01',
                'pengguna_id' => 'usr_emp_01',
                'nama_karyawan' => 'Budi Santoso',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Teknologi & IT',
                'tipe_kepegawaian' => 'PERMANENT',
                'tanggal' => $today,
                'jam_masuk' => '07:50:12',
                'latitude' => -7.94389000,
                'longitude' => 112.63221000,
                'jarak_meter' => 12,
                'status_lokasi' => 'IN_OFFICE',
                'status_kehadiran' => 'ON_TIME',
                'menit_keterlambatan' => 0,
                'mode_kerja' => 'WFO',
                'catatan' => 'Presensi masuk tepat waktu di kantor pusat IndigoSpace',
                'status_sinkronisasi' => 'SYNCED',
            ],
            [
                'id' => 'att_today_02',
                'pengguna_id' => 'usr_emp_02',
                'nama_karyawan' => 'Siti Rahmawati',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Desain & Produk',
                'tipe_kepegawaian' => 'PERMANENT',
                'tanggal' => $today,
                'jam_masuk' => '07:54:30',
                'latitude' => -7.94391000,
                'longitude' => 112.63219000,
                'jarak_meter' => 18,
                'status_lokasi' => 'IN_OFFICE',
                'status_kehadiran' => 'ON_TIME',
                'menit_keterlambatan' => 0,
                'mode_kerja' => 'WFO',
                'catatan' => 'Presensi masuk pagi',
                'status_sinkronisasi' => 'SYNCED',
            ],
            [
                'id' => 'att_today_03',
                'pengguna_id' => 'usr_emp_03',
                'nama_karyawan' => 'Ahmad Fauzi',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Teknologi & IT',
                'tipe_kepegawaian' => 'CONTRACT',
                'tanggal' => $today,
                'jam_masuk' => '08:14:22',
                'latitude' => -7.94385000,
                'longitude' => 112.63225000,
                'jarak_meter' => 22,
                'status_lokasi' => 'IN_OFFICE',
                'status_kehadiran' => 'LATE',
                'menit_keterlambatan' => 14,
                'mode_kerja' => 'WFO',
                'catatan' => 'Terhambat kemacetan di perempatan Lowokwaru',
                'status_sinkronisasi' => 'SYNCED',
            ],
            [
                'id' => 'att_today_04',
                'pengguna_id' => 'usr_emp_04',
                'nama_karyawan' => 'Dewi Lestari',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'HR & Operasional',
                'tipe_kepegawaian' => 'PERMANENT',
                'tanggal' => $today,
                'jam_masuk' => '07:42:05',
                'latitude' => -7.94390000,
                'longitude' => 112.63220000,
                'jarak_meter' => 8,
                'status_lokasi' => 'IN_OFFICE',
                'status_kehadiran' => 'ON_TIME',
                'menit_keterlambatan' => 0,
                'mode_kerja' => 'WFO',
                'catatan' => 'Presensi masuk pagi',
                'status_sinkronisasi' => 'SYNCED',
            ],
            [
                'id' => 'att_today_05',
                'pengguna_id' => 'usr_emp_05',
                'nama_karyawan' => 'Rizky Pratama',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Pemasaran & Media',
                'tipe_kepegawaian' => 'PERMANENT',
                'tanggal' => $today,
                'jam_masuk' => '08:02:15',
                'latitude' => -7.95000000,
                'longitude' => 112.62000000,
                'jarak_meter' => 1250,
                'status_lokasi' => 'WFH_APPROVED',
                'status_kehadiran' => 'ON_TIME',
                'menit_keterlambatan' => 0,
                'mode_kerja' => 'WFH',
                'catatan' => 'WFH terjadwal sprint campaign media sosial',
                'status_sinkronisasi' => 'SYNCED',
            ],
            [
                'id' => 'att_today_06',
                'pengguna_id' => 'usr_emp_06',
                'nama_karyawan' => 'Nadia Putri',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Pemasaran & Media',
                'tipe_kepegawaian' => 'INTERN',
                'tanggal' => $today,
                'jam_masuk' => '07:48:40',
                'latitude' => -7.94392000,
                'longitude' => 112.63222000,
                'jarak_meter' => 25,
                'status_lokasi' => 'IN_OFFICE',
                'status_kehadiran' => 'ON_TIME',
                'menit_keterlambatan' => 0,
                'mode_kerja' => 'WFO',
                'catatan' => 'Presensi masuk magang tepat waktu',
                'status_sinkronisasi' => 'SYNCED',
            ],
            [
                'id' => 'att_today_07',
                'pengguna_id' => 'usr_emp_07',
                'nama_karyawan' => 'Dimas Wahyudi',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Teknologi & IT',
                'tipe_kepegawaian' => 'INTERN',
                'tanggal' => $today,
                'jam_masuk' => '07:51:18',
                'latitude' => -7.94388000,
                'longitude' => 112.63218000,
                'jarak_meter' => 15,
                'status_lokasi' => 'IN_OFFICE',
                'status_kehadiran' => 'ON_TIME',
                'menit_keterlambatan' => 0,
                'mode_kerja' => 'WFO',
                'catatan' => 'Presensi masuk magang tepat waktu',
                'status_sinkronisasi' => 'SYNCED',
            ],

            // Presensi Kemarin (Riwayat Historis)
            [
                'id' => 'att_yest_01',
                'pengguna_id' => 'usr_emp_01',
                'nama_karyawan' => 'Budi Santoso',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Teknologi & IT',
                'tipe_kepegawaian' => 'PERMANENT',
                'tanggal' => $yesterday,
                'jam_masuk' => '07:45:00',
                'latitude' => -7.94390000,
                'longitude' => 112.63220000,
                'jarak_meter' => 10,
                'status_lokasi' => 'IN_OFFICE',
                'status_kehadiran' => 'ON_TIME',
                'menit_keterlambatan' => 0,
                'mode_kerja' => 'WFO',
                'catatan' => 'Hadir penuh waktu',
                'status_sinkronisasi' => 'SYNCED',
            ],
            [
                'id' => 'att_yest_02',
                'pengguna_id' => 'usr_emp_02',
                'nama_karyawan' => 'Siti Rahmawati',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Desain & Produk',
                'tipe_kepegawaian' => 'PERMANENT',
                'tanggal' => $yesterday,
                'jam_masuk' => '07:50:00',
                'latitude' => -7.94391000,
                'longitude' => 112.63221000,
                'jarak_meter' => 14,
                'status_lokasi' => 'IN_OFFICE',
                'status_kehadiran' => 'ON_TIME',
                'menit_keterlambatan' => 0,
                'mode_kerja' => 'WFO',
                'catatan' => 'Hadir penuh waktu',
                'status_sinkronisasi' => 'SYNCED',
            ],
            [
                'id' => 'att_yest_04',
                'pengguna_id' => 'usr_emp_04',
                'nama_karyawan' => 'Dewi Lestari',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'HR & Operasional',
                'tipe_kepegawaian' => 'PERMANENT',
                'tanggal' => $yesterday,
                'jam_masuk' => '07:40:00',
                'latitude' => -7.94390000,
                'longitude' => 112.63220000,
                'jarak_meter' => 5,
                'status_lokasi' => 'IN_OFFICE',
                'status_kehadiran' => 'ON_TIME',
                'menit_keterlambatan' => 0,
                'mode_kerja' => 'WFO',
                'catatan' => 'Hadir penuh waktu',
                'status_sinkronisasi' => 'SYNCED',
            ],
        ];

        foreach ($attendance as $att) {
            DB::table('presensi')->updateOrInsert(
                ['id' => $att['id']],
                array_merge($att, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // 4. Data Pengajuan Izin & Cuti Realistis
        $leaves = [
            [
                'id' => 'leave_001',
                'pengguna_id' => 'usr_emp_02',
                'nama_karyawan' => 'Siti Rahmawati',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Desain & Produk',
                'kategori_izin' => 'LEAVE',
                'tanggal_mulai' => '2026-11-10',
                'tanggal_selesai' => '2026-11-11',
                'jumlah_hari' => 2,
                'alasan' => 'Cuti tahunan keperluan menghadiri acara pernikahan keluarga di Surabaya.',
                'nama_lampiran' => 'Undangan_Pernikahan_Keluarga.pdf',
                'data_lampiran' => null,
                'tipe_lampiran' => 'application/pdf',
                'status_pengajuan' => 'APPROVED',
                'waktu_pengajuan' => '2026-11-01 09:15',
                'disetujui_oleh' => 'Super Admin',
                'waktu_persetujuan' => '2026-11-01 10:30',
                'alasan_penolakan' => null,
            ],
            [
                'id' => 'leave_002',
                'pengguna_id' => 'usr_emp_03',
                'nama_karyawan' => 'Ahmad Fauzi',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Teknologi & IT',
                'kategori_izin' => 'SICK',
                'tanggal_mulai' => '2026-10-28',
                'tanggal_selesai' => '2026-10-28',
                'jumlah_hari' => 1,
                'alasan' => 'Demam tinggi dan radang tenggorokan, dianjurkan istirahat dokter.',
                'nama_lampiran' => 'Surat_Keterangan_Dokter_Klinik_UB.pdf',
                'data_lampiran' => null,
                'tipe_lampiran' => 'application/pdf',
                'status_pengajuan' => 'APPROVED',
                'waktu_pengajuan' => '2026-10-28 07:30',
                'disetujui_oleh' => 'Super Admin',
                'waktu_persetujuan' => '2026-10-28 08:00',
                'alasan_penolakan' => null,
            ],
            [
                'id' => 'leave_003',
                'pengguna_id' => 'usr_emp_01',
                'nama_karyawan' => 'Budi Santoso',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Teknologi & IT',
                'kategori_izin' => 'BUSINESS_TRIP',
                'tanggal_mulai' => '2026-11-05',
                'tanggal_selesai' => '2026-11-06',
                'jumlah_hari' => 2,
                'alasan' => 'Dinas luar kota mewakili tim pengembang dalam koordinasi integrasi API Telkom Indigo di Surabaya.',
                'nama_lampiran' => 'Surat_Tugas_Dinas_Luar_Indiekraf.pdf',
                'data_lampiran' => null,
                'tipe_lampiran' => 'application/pdf',
                'status_pengajuan' => 'APPROVED',
                'waktu_pengajuan' => '2026-11-02 11:00',
                'disetujui_oleh' => 'Super Admin',
                'waktu_persetujuan' => '2026-11-02 13:45',
                'alasan_penolakan' => null,
            ],
            [
                'id' => 'leave_004',
                'pengguna_id' => 'usr_emp_05',
                'nama_karyawan' => 'Rizky Pratama',
                'foto_karyawan' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=256&q=80',
                'divisi' => 'Pemasaran & Media',
                'kategori_izin' => 'LEAVE',
                'tanggal_mulai' => '2026-11-06',
                'tanggal_selesai' => '2026-11-06',
                'jumlah_hari' => 1,
                'alasan' => 'Izin keperluan pribadi: Pengurusan perpanjangan SIM dan administrasi kependudukan di kantor Samsat.',
                'nama_lampiran' => null,
                'data_lampiran' => null,
                'tipe_lampiran' => null,
                'status_pengajuan' => 'PENDING',
                'waktu_pengajuan' => '2026-11-03 08:30',
                'disetujui_oleh' => null,
                'waktu_persetujuan' => null,
                'alasan_penolakan' => null,
            ],
        ];

        foreach ($leaves as $leave) {
            DB::table('pengajuan_izin')->updateOrInsert(
                ['id' => $leave['id']],
                array_merge($leave, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // 5. Data Logbook Harian (Daily Task Logs)
        $dailyLogs = [
            [
                'pengguna_id' => 'usr_emp_01',
                'nama_karyawan' => 'Budi Santoso',
                'tanggal' => $today,
                'daftar_tugas' => json_encode([
                    'Review dan merging Pull Request modul presensi geofencing radar',
                    'Optimasi query MySQL dan penyesuaian penamaan tabel Bahasa Indonesia untuk TA',
                    'Uji beban endpoint REST API Laravel Sanctum dengan 100 concurrent requests',
                ]),
                'catatan' => 'Semua modul backend berjalan dengan response time < 50ms.',
            ],
            [
                'pengguna_id' => 'usr_emp_02',
                'nama_karyawan' => 'Siti Rahmawati',
                'tanggal' => $today,
                'daftar_tugas' => json_encode([
                    'Finalisasi prototype high-fidelity komponen drawer detail karyawan di Figma',
                    'Uji keterbacaan kontras warna kartu kehadiran sesuai standar WCAG AAA',
                    'Menyiapkan icon asset SVG untuk modul logbook dan persetujuan cuti',
                ]),
                'catatan' => 'Design token sudah disinkronkan ke repositori.',
            ],
            [
                'pengguna_id' => 'usr_emp_03',
                'nama_karyawan' => 'Ahmad Fauzi',
                'tanggal' => $today,
                'daftar_tugas' => json_encode([
                    'Implementasi service worker caching offline-first untuk presensi masuk',
                    'Refactoring fungsi kalkulasi radius haversine GPS di sisi klien',
                    'Pengujian simulasi koneksi internet lambat pada pengambilan lokasi kantor',
                ]),
                'catatan' => 'Caching offline terverifikasi berfungsi normal.',
            ],
            [
                'pengguna_id' => 'usr_emp_04',
                'nama_karyawan' => 'Dewi Lestari',
                'tanggal' => $today,
                'daftar_tugas' => json_encode([
                    'Verifikasi berkas biodata & nomor rekening 2 karyawan magang baru',
                    'Rekapitulasi persentase kehadiran bulanan divisi Teknologi dan Pemasaran',
                    'Sosialisasi kebijakan toleransi jam masuk 08:00 WIB via ruang diskusi',
                ]),
                'catatan' => 'Seluruh data kepegawaian telah terarsip lengkap.',
            ],
            [
                'pengguna_id' => 'usr_emp_05',
                'nama_karyawan' => 'Rizky Pratama',
                'tanggal' => $today,
                'daftar_tugas' => json_encode([
                    'Analisis metrik engagement postingan kampanye program magang Indiekraf',
                    'Penyusunan laporan konversi traffic landing page minggu ke-1 November',
                    'Riset kata kunci SEO seputar ekosistem startup digital di Malang Raya',
                ]),
                'catatan' => 'Engagement rate meningkat 18% dibanding minggu lalu.',
            ],
            [
                'pengguna_id' => 'usr_emp_06',
                'nama_karyawan' => 'Nadia Putri',
                'tanggal' => $today,
                'daftar_tugas' => json_encode([
                    'Menulis draf 2 artikel blog seputar inovasi digital dan internship Indiekraf',
                    'Penyusunan jadwal feed, carousel, dan reels Instagram untuk pekan ini',
                    'Wawancara singkat dengan peserta magang batch terdahulu untuk testimoni',
                ]),
                'catatan' => 'Artikel siap masuk tahap review editorial.',
            ],
            [
                'pengguna_id' => 'usr_emp_07',
                'nama_karyawan' => 'Dimas Wahyudi',
                'tanggal' => $today,
                'daftar_tugas' => json_encode([
                    'Eksekusi automated test suite Playwright E2E pada skenario login & presensi',
                    'Penyusunan laporan bug responsivitas antarmuka di layar resolusi tablet',
                    'Verifikasi 78 unit test verifikasi sistem Absenku secara menyeluruh',
                ]),
                'catatan' => 'Semua 78 unit test pass 100%.',
            ],
        ];

        foreach ($dailyLogs as $log) {
            DB::table('logbook_harian')->insert(array_merge($log, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 6. Data Tugas Proyek (Kanban Tasks)
        $tasks = [
            [
                'id' => 'task_kanban_01',
                'judul_tugas' => 'Audit Keamanan & Penetrasi Endpoint API Absenku',
                'deskripsi' => 'Melakukan security audit pada endpoint otentikasi Sanctum, validasi CSRF, dan sanitasi input form untuk mencegah injeksi SQL.',
                'status_tugas' => 'TODO',
                'prioritas' => 'URGENT',
                'ditugaskan_ke' => 'usr_emp_01',
                'nama_petugas' => 'Budi Santoso',
                'foto_petugas' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=256&q=80',
                'daftar_anggota' => json_encode([
                    ['id' => 'usr_emp_01', 'name' => 'Budi Santoso', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=256&q=80'],
                    ['id' => 'usr_emp_07', 'name' => 'Dimas Wahyudi', 'avatar' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&w=256&q=80'],
                ]),
                'dibuat_oleh' => 'usr_super_admin',
                'tenggat_waktu' => '2026-11-15',
                'lampiran' => json_encode([]),
            ],
            [
                'id' => 'task_kanban_02',
                'judul_tugas' => 'Pengembangan Fitur Rekapitulasi Presensi format Excel & PDF',
                'deskripsi' => 'Menyediakan tombol ekspor laporan presensi bulanan lengkap dengan filter divisi, rentang tanggal, dan status keterlambatan.',
                'status_tugas' => 'IN_PROGRESS',
                'prioritas' => 'HIGH',
                'ditugaskan_ke' => 'usr_emp_01',
                'nama_petugas' => 'Budi Santoso',
                'daftar_anggota' => json_encode([
                    ['id' => 'usr_emp_01', 'name' => 'Budi Santoso', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=256&q=80'],
                ]),
                'dibuat_oleh' => 'usr_super_admin',
                'tenggat_waktu' => '2026-11-12',
                'lampiran' => json_encode([]),
            ],
            [
                'id' => 'task_kanban_03',
                'judul_tugas' => 'Redesain UI Kalender Kehadiran & Timepicker Responsif',
                'deskripsi' => 'Menyempurnakan tata letak kalender mini dan pemilih jam presensi agar lebih ramah sentuhan (touch-friendly) di perangkat mobile.',
                'status_tugas' => 'IN_PROGRESS',
                'prioritas' => 'MEDIUM',
                'ditugaskan_ke' => 'usr_emp_02',
                'nama_petugas' => 'Siti Rahmawati',
                'daftar_anggota' => json_encode([
                    ['id' => 'usr_emp_02', 'name' => 'Siti Rahmawati', 'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=256&q=80'],
                ]),
                'dibuat_oleh' => 'usr_super_admin',
                'tenggat_waktu' => '2026-11-10',
                'lampiran' => json_encode([]),
            ],
            [
                'id' => 'task_kanban_04',
                'judul_tugas' => 'Migrasi Skema Database ke Bahasa Indonesia Akademik (Tugas Akhir)',
                'deskripsi' => 'Mengubah nama seluruh tabel dan atribut database dari Bahasa Inggris menjadi istilah baku Bahasa Indonesia untuk penyusunan laporan TA.',
                'status_tugas' => 'DONE',
                'prioritas' => 'HIGH',
                'ditugaskan_ke' => 'usr_emp_01',
                'nama_petugas' => 'Budi Santoso',
                'daftar_anggota' => json_encode([
                    ['id' => 'usr_emp_01', 'name' => 'Budi Santoso', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=256&q=80'],
                ]),
                'dibuat_oleh' => 'usr_super_admin',
                'tenggat_waktu' => '2026-11-03',
                'lampiran' => json_encode([]),
            ],
            [
                'id' => 'task_kanban_05',
                'judul_tugas' => 'Pelaksanaan Pengujian Otomatis Playwright E2E Suite',
                'deskripsi' => 'Membuat dan mengeksekusi 13 skenario pengujian fungsional browser nyata dari pendaftaran karyawan hingga validasi radius geofence kantor.',
                'status_tugas' => 'DONE',
                'prioritas' => 'HIGH',
                'ditugaskan_ke' => 'usr_emp_07',
                'nama_petugas' => 'Dimas Wahyudi',
                'daftar_anggota' => json_encode([
                    ['id' => 'usr_emp_07', 'name' => 'Dimas Wahyudi', 'avatar' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&w=256&q=80'],
                ]),
                'dibuat_oleh' => 'usr_super_admin',
                'tenggat_waktu' => '2026-11-03',
                'lampiran' => json_encode([]),
            ],
        ];

        foreach ($tasks as $task) {
            DB::table('tugas_proyek')->updateOrInsert(
                ['id' => $task['id']],
                array_merge($task, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // 7. Data Topik Diskusi & Pesan Obrolan
        $topics = [
            [
                'id' => 'topic_01',
                'judul_topik' => 'Diskusi Rilis Absenku Versi 2.0 & Evaluasi Fitur',
                'status_topik' => 'OPEN',
                'id_anggota_ditandai' => json_encode(['usr_emp_01', 'usr_emp_02', 'usr_emp_03', 'usr_emp_04']),
                'berkas' => json_encode([]),
                'tautan' => json_encode([]),
                'gambar' => json_encode([]),
                'catatan_singkat' => 'Evaluasi rilis arsitektur hybrid React + Laravel untuk lingkungan Tugas Akhir.',
                'catatan_lengkap' => 'Silakan sampaikan catatan dan saran perbaikan modul presensi, logbook, dan manajemen cuti.',
                'dibuat_oleh_id' => 'usr_super_admin',
                'dibuat_oleh_nama' => 'Super Admin',
                'dibuat_oleh_foto' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=256&q=80',
            ],
            [
                'id' => 'topic_02',
                'judul_topik' => 'Pengumuman Jam Kerja & Kebijakan Lembur Bulan November',
                'status_topik' => 'OPEN',
                'id_anggota_ditandai' => json_encode(['usr_emp_04', 'usr_emp_05', 'usr_emp_06']),
                'berkas' => json_encode([]),
                'tautan' => json_encode([]),
                'gambar' => json_encode([]),
                'catatan_singkat' => 'Pemberlakuan jam masuk standar pukul 08:00 WIB dan jam pulang 17:00 WIB.',
                'catatan_lengkap' => 'Seluruh staf dan intern wajib melakukan presensi masuk di dalam radius 100 meter dari IndigoSpace Malang.',
                'dibuat_oleh_id' => 'usr_emp_04',
                'dibuat_oleh_nama' => 'Dewi Lestari',
                'dibuat_oleh_foto' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=256&q=80',
            ],
        ];

        foreach ($topics as $topic) {
            DB::table('topik_diskusi')->updateOrInsert(
                ['id' => $topic['id']],
                array_merge($topic, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $messages = [
            [
                'id' => 'msg_01',
                'topik_id' => 'topic_01',
                'pengguna_id' => 'usr_super_admin',
                'nama_pengirim' => 'Super Admin',
                'foto_pengirim' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=256&q=80',
                'peran_pengirim' => 'SUPER_ADMIN',
                'tipe_kepegawaian' => 'PERMANENT',
                'isi_pesan' => 'Selamat pagi rekan-rekan semua. Sistem Absenku Versi 2.0 kini telah terhubung penuh dengan backend Laravel dan database MySQL XAMPP. Silakan mulai melakukan presensi harian.',
                'waktu_kirim' => '2026-11-03 08:05:00',
                'kanal' => 'general',
                'lampiran' => json_encode([]),
            ],
            [
                'id' => 'msg_02',
                'topik_id' => 'topic_01',
                'pengguna_id' => 'usr_emp_01',
                'nama_pengirim' => 'Budi Santoso',
                'foto_pengirim' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=256&q=80',
                'peran_pengirim' => 'EMPLOYEE',
                'tipe_kepegawaian' => 'PERMANENT',
                'isi_pesan' => 'Siap Pak Super Admin, endpoint API presensi dan integrasi skema Bahasa Indonesia untuk Tugas Akhir sudah berjalan optimal di server.',
                'waktu_kirim' => '2026-11-03 08:08:12',
                'kanal' => 'general',
                'lampiran' => json_encode([]),
            ],
            [
                'id' => 'msg_03',
                'topik_id' => 'topic_01',
                'pengguna_id' => 'usr_emp_02',
                'nama_pengirim' => 'Siti Rahmawati',
                'foto_pengirim' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=256&q=80',
                'peran_pengirim' => 'EMPLOYEE',
                'tipe_kepegawaian' => 'PERMANENT',
                'isi_pesan' => 'Tampilan antarmuka dashboard dan tata letak kalender presensi juga sudah responsif untuk desktop maupun smartphone.',
                'waktu_kirim' => '2026-11-03 08:11:45',
                'kanal' => 'general',
                'lampiran' => json_encode([]),
            ],
        ];

        foreach ($messages as $msg) {
            DB::table('pesan_diskusi')->updateOrInsert(
                ['id' => $msg['id']],
                array_merge($msg, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
