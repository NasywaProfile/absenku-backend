<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveUser(string $id, string $role): User
    {
        return User::create([
            'id' => $id,
            'nama' => $role === 'ADMIN' ? 'PIC Tim' : 'Anggota Tim',
            'email' => $id . '@absenku.test',
            'password' => Hash::make('Password123!'),
            'peran' => $role,
            'status' => 'ACTIVE',
            'tipe_kepegawaian' => 'PERMANENT',
            'divisi' => 'Teknologi & IT',
            'jabatan' => 'Staff',
            'foto_profil' => '',
            'nomor_telepon' => '08123456789',
            'tanggal_bergabung' => '2025-01-01',
            'sisa_cuti' => 12,
            'waktu_daftar' => '2025-01-01 08:00',
            'profil_lengkap' => true,
        ]);
    }

    public function test_pic_can_create_and_update_kanban_task_with_oversized_empty_and_valid_assignee_avatar(): void
    {
        $pic = $this->makeActiveUser('usr_pic_reg', 'ADMIN');
        $employee = $this->makeActiveUser('usr_emp_reg', 'EMPLOYEE');
        Sanctum::actingAs($pic);

        $oversizedAvatar = 'data:image/png;base64,' . str_repeat('A', 70000);

        $createRes = $this->postJson('/api/kanban-tasks', [
            'id' => 'task_1794458000_reg1',
            'title' => 'Task Avatar Oversized',
            'description' => 'Testing 65KB TEXT guard on MySQL',
            'status' => 'TODO',
            'priority' => 'MEDIUM',
            'assigned_to' => $employee->id,
            'assigned_name' => $employee->nama,
            'assigned_avatar' => $oversizedAvatar,
            'due_date' => '',
            'attachments' => [],
        ]);

        $createRes->assertCreated();
        $this->assertNull($createRes->json('task.foto_petugas'));
        $this->assertSame($pic->id, $createRes->json('task.dibuat_oleh'));

        $emptyUpdateRes = $this->putJson('/api/kanban-tasks/task_1794458000_reg1', [
            'assigned_avatar' => '   ',
        ]);
        $emptyUpdateRes->assertOk();
        $this->assertNull($emptyUpdateRes->json('task.foto_petugas'));

        $validUpdateRes = $this->putJson('/api/kanban-tasks/task_1794458000_reg1', [
            'assigned_avatar' => '/storage/uploads/avatar.png',
        ]);
        $validUpdateRes->assertOk();
        $this->assertSame('/storage/uploads/avatar.png', $validUpdateRes->json('task.foto_petugas'));

        $unsafeUpdateRes = $this->putJson('/api/kanban-tasks/task_1794458000_reg1', [
            'assigned_avatar' => 'javascript:alert(1)',
        ]);
        $unsafeUpdateRes->assertStatus(422);
    }

    public function test_kanban_task_syncs_review_notes_submission_notes_and_activities(): void
    {
        $pic = $this->makeActiveUser('usr_pic_rev', 'ADMIN');
        $employee = $this->makeActiveUser('usr_emp_rev', 'EMPLOYEE');

        Sanctum::actingAs($pic);
        $res = $this->postJson('/api/kanban-tasks', [
            'id' => 'task_rev_sync_01',
            'title' => 'Tugas dengan Chat & Revisi',
            'description' => 'Kerjakan modul ini',
            'status' => 'IN_PROGRESS',
            'priority' => 'HIGH',
            'assigned_to' => $employee->id,
            'assigned_name' => $employee->nama,
            'activities' => [
                [
                    'id' => 'act_01',
                    'type' => 'COMMENT_ADDED',
                    'userId' => $pic->id,
                    'userName' => $pic->nama,
                    'notes' => 'Harap selesaikan sebelum sore',
                    'timestamp' => '2026-11-17 14:00',
                ],
            ],
        ]);
        $res->assertCreated();
        $this->assertCount(1, $res->json('task.activities'));
        $this->assertSame('Harap selesaikan sebelum sore', $res->json('task.activities.0.notes'));

        // Karyawan menyerahkan hasil preview dengan catatan
        Sanctum::actingAs($employee);
        $subRes = $this->putJson('/api/kanban-tasks/task_rev_sync_01', [
            'status' => 'DONE',
            'submission_notes' => 'Berikut hasil desain sudah siap dicek',
            'activities' => [
                [
                    'id' => 'act_01',
                    'type' => 'COMMENT_ADDED',
                    'userId' => $pic->id,
                    'userName' => $pic->nama,
                    'notes' => 'Harap selesaikan sebelum sore',
                    'timestamp' => '2026-11-17 14:00',
                ],
                [
                    'id' => 'act_02',
                    'type' => 'SUBMITTED',
                    'userId' => $employee->id,
                    'userName' => $employee->nama,
                    'notes' => 'Berikut hasil desain sudah siap dicek',
                    'timestamp' => '2026-11-17 15:00',
                ],
            ],
        ]);
        $subRes->assertOk();
        $this->assertSame('DONE', $subRes->json('task.status'));
        $this->assertSame('Berikut hasil desain sudah siap dicek', $subRes->json('task.submission_notes'));
        $this->assertCount(2, $subRes->json('task.activities'));

        // PIC meminta revisi dengan arahan perbaikan
        Sanctum::actingAs($pic);
        $revRes = $this->putJson('/api/kanban-tasks/task_rev_sync_01', [
            'status' => 'IN_PROGRESS',
            'review_notes' => 'Tolong font judul diganti Inter dan warna tombol diubah biru',
            'activities' => [
                [
                    'id' => 'act_01',
                    'type' => 'COMMENT_ADDED',
                    'userId' => $pic->id,
                    'userName' => $pic->nama,
                    'notes' => 'Harap selesaikan sebelum sore',
                    'timestamp' => '2026-11-17 14:00',
                ],
                [
                    'id' => 'act_02',
                    'type' => 'SUBMITTED',
                    'userId' => $employee->id,
                    'userName' => $employee->nama,
                    'notes' => 'Berikut hasil desain sudah siap dicek',
                    'timestamp' => '2026-11-17 15:00',
                ],
                [
                    'id' => 'act_03',
                    'type' => 'REVISION_REQUESTED',
                    'userId' => $pic->id,
                    'userName' => $pic->nama,
                    'notes' => 'Tolong font judul diganti Inter dan warna tombol diubah biru',
                    'timestamp' => '2026-11-17 15:30',
                ],
            ],
        ]);
        $revRes->assertOk();
        $this->assertSame('IN_PROGRESS', $revRes->json('task.status'));
        $this->assertSame('Tolong font judul diganti Inter dan warna tombol diubah biru', $revRes->json('task.review_notes'));
        $this->assertCount(3, $revRes->json('task.activities'));

        // GET /api/kanban-tasks memastikan semua field terserialisasi dengan lengkap
        Sanctum::actingAs($employee);
        $getRes = $this->getJson('/api/kanban-tasks');
        $getRes->assertOk();
        $fetched = collect($getRes->json())->firstWhere('id', 'task_rev_sync_01');
        $this->assertNotNull($fetched);
        $this->assertSame('Tolong font judul diganti Inter dan warna tombol diubah biru', $fetched['review_notes']);
        $this->assertSame('Berikut hasil desain sudah siap dicek', $fetched['submission_notes']);
        $this->assertCount(3, $fetched['activities']);
    }
}
