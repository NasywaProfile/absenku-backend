<?php

namespace Tests\Feature;

use App\Http\Controllers\EmailController;
use App\Models\AttendanceRecord;
use App\Models\ChatMessage;
use App\Models\ChatTopic;
use App\Models\KanbanTask;
use App\Models\LeaveRequest;
use App\Models\OfficeConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $employee;
    private User $otherEmployee;
    private User $pendingUser;

    protected function setUp(): void
    {
        parent::setUp();

        OfficeConfig::create([
            'nama_kantor' => 'IndigoSpace Malang',
            'alamat_kantor' => 'Jl. Simpang Borobudur No. 1',
            'latitude' => -7.94390000,
            'longitude' => 112.63220000,
            'radius_geofence_meter' => 100,
            'jam_masuk' => '08:00',
            'jam_pulang' => '17:00',
            'toleransi_keterlambatan_menit' => 0,
            'status_geofence_aktif' => true,
        ]);

        $this->superAdmin = User::create([
            'id' => 'usr_super_admin',
            'nama' => 'Super Admin',
            'email' => 'superadmin@indiekraf.test',
            'password' => Hash::make('SecretSA123!'),
            'peran' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
            'tipe_kepegawaian' => 'PERMANENT',
            'divisi' => 'Direksi',
            'jabatan' => 'Super Admin',
            'profil_lengkap' => true,
        ]);

        $this->admin = User::create([
            'id' => 'usr_admin_01',
            'nama' => 'Admin Operasional',
            'email' => 'admin@indiekraf.test',
            'password' => Hash::make('SecretAdmin123!'),
            'peran' => 'ADMIN',
            'status' => 'ACTIVE',
            'tipe_kepegawaian' => 'PERMANENT',
            'divisi' => 'HR',
            'jabatan' => 'HR Admin',
            'profil_lengkap' => true,
        ]);

        $this->employee = User::create([
            'id' => 'usr_emp_01',
            'nama' => 'Karyawan Satu',
            'email' => 'emp1@indiekraf.test',
            'password' => Hash::make('SecretEmp123!'),
            'peran' => 'EMPLOYEE',
            'status' => 'ACTIVE',
            'tipe_kepegawaian' => 'CONTRACT',
            'divisi' => 'Engineering',
            'jabatan' => 'Developer',
            'nomor_rekening' => '1234567890',
            'nomor_kontak_darurat' => '081111111111',
            'profil_lengkap' => true,
        ]);

        $this->otherEmployee = User::create([
            'id' => 'usr_emp_02',
            'nama' => 'Karyawan Dua',
            'email' => 'emp2@indiekraf.test',
            'password' => Hash::make('SecretEmp234!'),
            'peran' => 'EMPLOYEE',
            'status' => 'ACTIVE',
            'tipe_kepegawaian' => 'CONTRACT',
            'divisi' => 'Design',
            'jabatan' => 'UI Designer',
            'nomor_rekening' => '9988776655',
            'nomor_kontak_darurat' => '082222222222',
            'profil_lengkap' => true,
        ]);

        $this->pendingUser = User::create([
            'id' => 'usr_pending_01',
            'nama' => 'Pendaftar Baru',
            'email' => 'pending@indiekraf.test',
            'password' => Hash::make('PendingPass123!'),
            'peran' => 'EMPLOYEE',
            'status' => 'PENDING_APPROVAL',
            'tipe_kepegawaian' => 'INTERN',
            'divisi' => 'IT',
            'jabatan' => 'Intern',
            'profil_lengkap' => false,
        ]);
    }

    public function test_unauthenticated_requests_are_blocked_and_legacy_bypass_routes_are_removed(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
        $this->postJson('/api/users/usr_emp_01/role', ['role' => 'SUPER_ADMIN'])->assertStatus(401);
        $this->postJson('/api/users/usr_pending_01/approve', ['role' => 'ADMIN'])->assertStatus(401);
        $this->deleteJson('/api/attendance', ['user_id' => 'usr_emp_01'])->assertStatus(401);
        $this->getJson('/api/kanban-tasks')->assertStatus(401);
        $this->postJson('/api/upload')->assertStatus(401);
        $this->postJson('/api/send-email', ['to' => 'x@example.com', 'subject' => 'hi'])->assertStatus(401);
        $this->getJson('/api/user-status?email=superadmin@indiekraf.test')->assertStatus(401);

        // Rute bypass dan debug berbahaya wajib sudah dihapus (404)
        $this->getJson('/api/repair-superadmin')->assertStatus(404);
        $this->getJson('/api/test-email')->assertStatus(404);
        $this->postJson('/api/profile/direct', ['nama' => 'Hack'])->assertStatus(404);
        $this->postJson('/api/users/usr_pending_01/approve/direct', ['role' => 'SUPER_ADMIN'])->assertStatus(404);
    }

    public function test_employee_cannot_perform_admin_actions_or_read_other_users_financial_pii(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/users/usr_emp_01/role', ['role' => 'SUPER_ADMIN'])->assertStatus(403);
        $this->postJson('/api/users/usr_pending_01/approve', ['role' => 'EMPLOYEE'])->assertStatus(403);
        $this->postJson('/api/users/usr_pending_01/reject')->assertStatus(403);
        $this->postJson('/api/users/usr_emp_02/employment-type', ['employment_type' => 'PERMANENT'])->assertStatus(403);
        $this->deleteJson('/api/users/usr_emp_02')->assertStatus(403);
        $this->deleteJson('/api/attendance', ['user_id' => 'usr_emp_01'])->assertStatus(403);
        $this->postJson('/api/kanban-tasks', ['judul_tugas' => 'Tugas Palsu'])->assertStatus(403);

        // Tidak boleh mengedit profil pengguna lain
        $this->putJson('/api/profile', ['id' => 'usr_emp_02', 'nama' => 'Dibajak'])->assertStatus(403);

        // Tidak boleh melihat nomor rekening karyawan lain di GET /api/users
        $res = $this->getJson('/api/users')->assertStatus(200);
        $users = collect($res->json());
        $other = $users->firstWhere('id', 'usr_emp_02');
        $self = $users->firstWhere('id', 'usr_emp_01');
        $this->assertNotNull($other);
        $this->assertArrayNotHasKey('nomor_rekening', $other);
        $this->assertArrayNotHasKey('bank_account_number', $other);
        $this->assertArrayNotHasKey('nomor_kontak_darurat', $other);
        $this->assertSame('1234567890', $self['nomor_rekening']);

        // Karyawan tidak boleh meng-approve pengajuan cuti
        $leave = LeaveRequest::create([
            'id' => 'lve_test_1',
            'pengguna_id' => $this->employee->id,
            'nama_karyawan' => $this->employee->nama,
            'kategori_izin' => 'LEAVE',
            'tanggal_mulai' => '2026-11-12',
            'tanggal_selesai' => '2026-11-12',
            'jumlah_hari' => 1,
            'alasan' => 'Cuti',
            'status_pengajuan' => 'PENDING',
        ]);
        $this->postJson("/api/leaves/{$leave->id}/approve")->assertStatus(403);
    }

    public function test_admin_rbac_limits_and_super_admin_privileges(): void
    {
        // Admin biasa tidak boleh mengangkat user menjadi SUPER_ADMIN atau menolak/menghapus SUPER_ADMIN
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/users/usr_pending_01/approve', ['role' => 'SUPER_ADMIN'])->assertStatus(403);
        $this->postJson('/api/users/usr_admin_01/role', ['role' => 'SUPER_ADMIN'])->assertStatus(403);
        $this->postJson('/api/users/usr_super_admin/reject')->assertStatus(403);
        $this->deleteJson('/api/users/usr_super_admin')->assertStatus(403);

        // Admin biasa bisa menyetujui pendaftar menjadi EMPLOYEE
        $this->postJson('/api/users/usr_pending_01/approve', ['role' => 'EMPLOYEE'])->assertStatus(200);
        $this->assertSame('ACTIVE', $this->pendingUser->fresh()->status);

        // Super Admin dapat menetapkan peran Admin
        Sanctum::actingAs($this->superAdmin);
        $this->postJson('/api/users/usr_pending_01/role', ['role' => 'ADMIN'])->assertStatus(200);
        $this->assertSame('ADMIN', $this->pendingUser->fresh()->peran);
    }

    public function test_pending_user_can_complete_onboarding_and_poll_status_but_cannot_access_active_routes(): void
    {
        $loginRes = $this->postJson('/api/login', [
            'email' => 'pending@indiekraf.test',
            'password' => 'PendingPass123!',
        ]);
        $loginRes->assertStatus(403);
        $token = $loginRes->json('token');
        $this->assertNotEmpty($token);

        // Dapat memeriksa statusnya sendiri dan memperbarui profil dengan token pending
        $this->withToken($token)
            ->getJson('/api/user-status')
            ->assertStatus(200)
            ->assertJsonPath('status', 'PENDING_APPROVAL');

        $this->withToken($token)
            ->putJson('/api/profile', ['alamat' => 'Jl. Merdeka 10', 'peran' => 'SUPER_ADMIN', 'status' => 'ACTIVE'])
            ->assertStatus(200);

        // Pastikan peran dan status TIDAK bisa dipalsukan lewat updateProfile
        $refreshed = $this->pendingUser->fresh();
        $this->assertSame('Jl. Merdeka 10', $refreshed->alamat);
        $this->assertSame('EMPLOYEE', $refreshed->peran);
        $this->assertSame('PENDING_APPROVAL', $refreshed->status);

        // Tidak bisa mengakses rute operasional selama belum ACTIVE
        $this->withToken($token)->getJson('/api/users')->assertStatus(403);
        $this->withToken($token)->postJson('/api/attendance', ['latitude' => -7.9439, 'longitude' => 112.6322])->assertStatus(403);
        $this->withToken($token)->getJson('/api/kanban-tasks')->assertStatus(403);
    }

    public function test_upload_controller_blocks_dangerous_files_and_validates_real_mime_and_size(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->employee);

        // PHP shell ditolak
        $phpFile = UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>');
        $this->postJson('/api/upload', ['file' => $phpFile])->assertStatus(422);

        // SVG XSS ditolak
        $svgFile = UploadedFile::fake()->createWithContent('xss.svg', '<svg onload="alert(1)"></svg>');
        $this->postJson('/api/upload', ['file' => $svgFile])->assertStatus(422);

        // Gambar minimal PNG & JPEG valid (tanpa ketergantungan ekstensi GD)
        $minimalPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $minimalJpg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xFF\xD9";

        // Double extension ditolak
        $doubleExt = UploadedFile::fake()->createWithContent('exploit.php.jpg', $minimalJpg);
        $this->postJson('/api/upload', ['file' => $doubleExt])->assertStatus(422);

        // Spoofed extension (isi PHP dinamai .jpg) ditolak karena finfo mendeteksi MIME bukan JPEG
        $spoofed = UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo "hi"; ?>');
        $this->postJson('/api/upload', ['file' => $spoofed])->assertStatus(422);

        // Gambar PNG valid diterima dan tidak mengandung domain duckdns
        $validImg = UploadedFile::fake()->createWithContent('selfie.png', $minimalPng);
        $okRes = $this->postJson('/api/upload', ['file' => $validImg]);
        $okRes->assertStatus(200);
        $this->assertStringNotContainsString('duckdns', $okRes->json('url'));

        // Dokumen PDF valid diterima
        $validPdf = UploadedFile::fake()->createWithContent('surat.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->postJson('/api/upload/document', ['document' => $validPdf])->assertStatus(200);
    }

    public function test_attendance_store_recalculates_distance_and_punctuality_server_side(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 11, 9, 15, 0, 'Asia/Jakarta'));

        Sanctum::actingAs($this->employee);

        // Klien mencoba memalsukan jarak=0, IN_OFFICE, ON_TIME, terlambat=0, jam_masuk=07:50 padahal koordinat 5 km dari kantor dan jam server 09:15 WIB
        $res = $this->postJson('/api/attendance', [
            'jam_masuk' => '07:50:00',
            'latitude' => -7.900000,
            'longitude' => 112.632200,
            'jarak_meter' => 0,
            'status_lokasi' => 'IN_OFFICE',
            'status_kehadiran' => 'ON_TIME',
            'menit_keterlambatan' => 0,
            'mode_kerja' => 'WFH',
        ]);

        $res->assertStatus(200);
        $record = $res->json('record');

        $this->assertGreaterThan(4000, $record['jarak_meter']);
        $this->assertSame('OUT_OF_RANGE', $record['status_lokasi']);
        $this->assertSame('WFO', $record['mode_kerja']);
        $this->assertSame('LATE', $record['status_kehadiran']);
        $this->assertSame(75, $record['menit_keterlambatan']);
        $this->assertSame('09:15:00', $record['jam_masuk']);

        // Tidak boleh mencatat ulang presensi hari yang sama untuk mengakali status terlambat
        $this->postJson('/api/attendance', [
            'latitude' => -7.94390000,
            'longitude' => 112.63220000,
        ])->assertStatus(409);

        // Tidak boleh mencatat presensi atas nama user lain
        $this->postJson('/api/attendance', [
            'user_id' => 'usr_emp_02',
            'latitude' => -7.94390000,
            'longitude' => 112.63220000,
        ])->assertStatus(403);

        Carbon::setTestNow();
    }

    public function test_send_email_prevents_open_relay_and_reads_from_config(): void
    {
        Sanctum::actingAs($this->employee);

        // Karyawan biasa dilarang mengirim email ke alamat eksternal sembarangan
        $this->postJson('/api/send-email', [
            'to' => 'stranger@external-spam.com',
            'subject' => 'Spam Test',
            'text' => 'Hello',
        ])->assertStatus(403);

        // Pastikan EmailController menggunakan config() bukan env() (aman saat config:cache)
        $source = file_get_contents(app_path('Http/Controllers/EmailController.php'));
        $this->assertSame(0, preg_match('/\benv\s*\(/', $source));

        // Simulasikan config sudah di-cache dengan Resend key & mailer aktif via Config::set
        Config::set('mail.default', 'smtp');
        Config::set('services.resend.key', 'test_resend_key');
        Config::set('services.resend.from_email', 'noreply@indiekraf.test');
        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'msg_123'], 200),
        ]);

        // Mengirim ke rekan kerja terdaftar berhasil
        $this->postJson('/api/send-email', [
            'to' => 'admin@indiekraf.test',
            'subject' => 'Notifikasi Pengajuan',
            'text' => 'Isi pesan',
        ])->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_strict_rbac_for_office_config_role_promotion_tasks_leaves_and_chat_idor(): void
    {
        // Admin biasa dilarang mengubah konfigurasi kantor & radius geofence (khusus SUPER_ADMIN)
        Sanctum::actingAs($this->admin);
        $this->putJson('/api/office-config', ['radius_geofence_meter' => 9999])->assertStatus(403);
        $this->postJson('/api/users/usr_pending_01/approve', ['role' => 'ADMIN'])->assertStatus(403);
        $this->postJson('/api/users/usr_emp_01/role', ['role' => 'ADMIN'])->assertStatus(403);
        $this->postJson('/api/users/usr_super_admin/employment-type', ['employment_type' => 'INTERN'])->assertStatus(403);

        // Hanya Super Admin yang bisa memperbarui konfigurasi kantor
        Sanctum::actingAs($this->superAdmin);
        $this->putJson('/api/office-config', ['radius_geofence_meter' => 150])->assertStatus(200);

        // Karyawan tidak boleh melihat pendaftar PENDING di GET /api/users maupun menyetujui tugas sendiri ke REVIEWED
        Sanctum::actingAs($this->employee);
        $usersRes = $this->getJson('/api/users')->assertStatus(200);
        $userIds = collect($usersRes->json())->pluck('id');
        $this->assertFalse($userIds->contains('usr_pending_01'));
        $otherEmp = collect($usersRes->json())->firstWhere('id', 'usr_emp_02');
        $this->assertArrayNotHasKey('sisa_cuti', $otherEmp);

        // Null Island (0, 0) harus ditolak pada presensi mandiri
        $this->postJson('/api/attendance', ['latitude' => 0, 'longitude' => 0])->assertStatus(422);

        $task = KanbanTask::create([
            'id' => 'tsk_rbac_test',
            'judul_tugas' => 'Tugas Audit',
            'status_tugas' => 'IN_PROGRESS',
            'prioritas' => 'HIGH',
            'ditugaskan_ke' => $this->employee->id,
            'dibuat_oleh' => $this->superAdmin->nama,
        ]);

        // Karyawan boleh memajukan status ke DONE, tetapi DILARANG memaksakan status REVIEWED (self-approve)
        $this->putJson("/api/kanban-tasks/{$task->id}", ['status_tugas' => 'REVIEWED'])->assertStatus(403);
        $this->putJson("/api/kanban-tasks/{$task->id}", ['status_tugas' => 'DONE'])->assertStatus(200);

        // Server menghitung jumlah_hari cuti otoritatif (mengabaikan input negatif klien) & mencegah double deduct
        $leaveRes = $this->postJson('/api/leaves', [
            'kategori_izin' => 'LEAVE',
            'tanggal_mulai' => '2026-11-20',
            'tanggal_selesai' => '2026-11-21',
            'jumlah_hari' => -50,
            'alasan' => 'Izin dua hari',
        ])->assertStatus(201);
        $this->assertSame(2, $leaveRes->json('leave.jumlah_hari'));
        $leaveId = $leaveRes->json('leave.id');

        // Admin biasa tidak boleh meng-approve izinnya sendiri, tetapi boleh meng-approve izin karyawan (1x saja)
        $this->employee->update(['sisa_cuti' => 12]);
        Sanctum::actingAs($this->admin);
        $ownLeave = LeaveRequest::create([
            'id' => 'lve_own_admin',
            'pengguna_id' => $this->admin->id,
            'nama_karyawan' => $this->admin->nama,
            'kategori_izin' => 'LEAVE',
            'tanggal_mulai' => '2026-11-25',
            'tanggal_selesai' => '2026-11-25',
            'jumlah_hari' => 1,
            'alasan' => 'Cuti admin',
            'status_pengajuan' => 'PENDING',
        ]);
        $this->postJson("/api/leaves/{$ownLeave->id}/approve")->assertStatus(403);
        $this->postJson("/api/leaves/{$leaveId}/approve")->assertStatus(200);
        $this->assertSame(10, $this->employee->fresh()->sisa_cuti);
        // Approve ulang wajib ditolak (409) agar sisa_cuti tidak terpotong dua kali
        $this->postJson("/api/leaves/{$leaveId}/approve")->assertStatus(409);
        $this->assertSame(10, $this->employee->fresh()->sisa_cuti);

        // Karyawan tidak boleh menghapus izin yang sudah APPROVED
        Sanctum::actingAs($this->employee);
        $this->deleteJson("/api/leaves/{$leaveId}")->assertStatus(403);

        // Anti-IDOR pada ChatTopic & ChatMessage (tidak boleh menimpa ID milik admin)
        ChatTopic::create([
            'id' => 'tpc_admin_owned',
            'judul_topik' => 'Pengumuman Resmi',
            'status_topik' => 'IN_PROGRESS',
            'dibuat_oleh_id' => $this->admin->id,
            'dibuat_oleh_nama' => $this->admin->nama,
        ]);
        $this->postJson('/api/chat/topics', [
            'id' => 'tpc_admin_owned',
            'judul_topik' => 'Topik Dibajak',
        ])->assertStatus(403);

        ChatMessage::create([
            'id' => 'msg_admin_owned',
            'topik_id' => 'tpc_admin_owned',
            'pengguna_id' => $this->admin->id,
            'nama_pengirim' => $this->admin->nama,
            'isi_pesan' => 'Instruksi Resmi',
            'waktu_kirim' => '09:00',
            'kanal' => 'general',
        ]);
        $this->postJson('/api/chat/messages', [
            'id' => 'msg_admin_owned',
            'topik_id' => 'tpc_admin_owned',
            'isi_pesan' => 'Pesan Dibajak',
        ])->assertStatus(403);

        // Pastikan security response headers aktif
        $headerRes = $this->getJson('/api/office-config')->assertStatus(200);
        $headerRes->assertHeader('X-Content-Type-Options', 'nosniff');
        $headerRes->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_no_hardcoded_default_passwords_in_seeder_and_superadmin_rotation_plus_backup_work(): void
    {
        $seederSource = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringNotContainsString('SuperAdminabsenkuMantap', $seederSource);
        $this->assertStringNotContainsString('Password123!', $seederSource);
        $this->assertStringNotContainsString('nasywadavina384@gmail.com', $seederSource);

        // Token lama Super Admin harus hangus setelah kredensial diganti lewat absenku:set-superadmin
        $oldToken = $this->superAdmin->createToken('old-session', ['*'])->plainTextToken;
        $this->assertSame(1, $this->superAdmin->tokens()->count());

        $exitCode = Artisan::call('absenku:set-superadmin', [
            '--email' => 'owner.baru@indiekraf.co.id',
            '--password' => 'StrongOwnerPass#2026!',
            '--name' => 'Pemilik Perusahaan',
        ]);
        $this->assertSame(0, $exitCode);

        $rotated = $this->superAdmin->fresh();
        $this->assertSame('owner.baru@indiekraf.co.id', $rotated->email);
        $this->assertTrue(Hash::check('StrongOwnerPass#2026!', $rotated->password));
        $this->assertSame(0, $rotated->tokens()->count());

        $this->withToken($oldToken)->getJson('/api/me')->assertStatus(401);
        $this->postJson('/api/login', [
            'email' => 'owner.baru@indiekraf.co.id',
            'password' => 'StrongOwnerPass#2026!',
        ])->assertStatus(200);

        // Verifikasi backup SQLite pada file sementara
        $origDefaultConn = config('database.default');
        $tmpSqlite = storage_path('app/test_backup_source.sqlite');
        File::ensureDirectoryExists(dirname($tmpSqlite));
        file_put_contents($tmpSqlite, 'sqlite-dummy-header');
        Config::set('database.connections.sqlite_backup_test', [
            'driver' => 'sqlite',
            'database' => $tmpSqlite,
        ]);
        Config::set('database.default', 'sqlite_backup_test');

        try {
            $backupExit = Artisan::call('db:backup', ['--keep-days' => 7]);
            $this->assertSame(0, $backupExit);

            $backupFiles = File::glob(storage_path('app/backups/absenku_sqlite_*.sqlite.gz'));
            $this->assertNotEmpty($backupFiles);

            File::delete($backupFiles);
        } finally {
            Config::set('database.default', $origDefaultConn);
            File::delete($tmpSqlite);
        }
    }
}
