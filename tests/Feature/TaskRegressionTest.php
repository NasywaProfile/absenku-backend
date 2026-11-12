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
}
