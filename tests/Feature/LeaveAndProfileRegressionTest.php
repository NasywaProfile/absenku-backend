<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveAndProfileRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_creation_truncates_office_mime_and_long_filename_and_bounds_avatar(): void
    {
        $oversizedAvatar = 'data:image/png;base64,' . str_repeat('B', 70000);

        $emp = User::create([
            'id' => 'usr_emp_leave_reg',
            'nama' => 'Karyawan Uji',
            'email' => 'emp_leave_reg@absenku.test',
            'password' => Hash::make('Password123!'),
            'peran' => 'EMPLOYEE',
            'status' => 'ACTIVE',
            'tipe_kepegawaian' => 'CONTRACT',
            'divisi' => 'Operasional',
            'jabatan' => 'Staff',
            'foto_profil' => $oversizedAvatar,
            'nomor_telepon' => '08123456789',
            'tanggal_bergabung' => '2025-01-01',
            'sisa_cuti' => 12,
            'waktu_daftar' => '2025-01-01 08:00',
            'profil_lengkap' => true,
        ]);

        Sanctum::actingAs($emp);

        $docxMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $longName = str_repeat('surat_dokter_', 25) . '.docx';

        $res = $this->postJson('/api/leaves', [
            'id' => 'leave_reg_docx_01',
            'category' => 'SICK',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-20',
            'reason' => 'Sakit demam',
            'attachment_name' => $longName,
            'attachment_data' => '/storage/uploads/surat.docx',
            'attachment_type' => $docxMime,
        ]);

        $res->assertCreated();
        $this->assertLessThanOrEqual(50, strlen((string) $res->json('leave.tipe_lampiran')));
        $this->assertLessThanOrEqual(255, mb_strlen((string) $res->json('leave.nama_lampiran')));
        $this->assertNull($res->json('leave.foto_karyawan'));
    }

    public function test_profile_update_rejects_oversized_inline_avatar(): void
    {
        $emp = User::create([
            'id' => 'usr_emp_prof_reg',
            'nama' => 'Karyawan Profil',
            'email' => 'emp_prof_reg@absenku.test',
            'password' => Hash::make('Password123!'),
            'peran' => 'EMPLOYEE',
            'status' => 'ACTIVE',
            'tipe_kepegawaian' => 'CONTRACT',
            'divisi' => 'Operasional',
            'jabatan' => 'Staff',
            'foto_profil' => '',
            'nomor_telepon' => '08123456789',
            'tanggal_bergabung' => '2025-01-01',
            'sisa_cuti' => 12,
            'waktu_daftar' => '2025-01-01 08:00',
            'profil_lengkap' => true,
        ]);

        Sanctum::actingAs($emp);

        $res = $this->putJson('/api/profile', [
            'avatar' => 'data:image/png;base64,' . str_repeat('C', 70000),
        ]);

        $res->assertStatus(422);
    }
}
