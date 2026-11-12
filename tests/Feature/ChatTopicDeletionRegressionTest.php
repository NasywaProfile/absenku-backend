<?php

namespace Tests\Feature;

use App\Models\ChatTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTopicDeletionRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function createTestUser(string $id, string $name, string $email, string $role): User
    {
        return User::create([
            'id' => $id,
            'nama' => $name,
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'peran' => $role,
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
    }

    public function test_deleting_shared_topic_hides_only_for_acting_user_until_all_participants_delete(): void
    {
        $accountA = $this->createTestUser('usr_chat_a', 'Akun A', 'a@absenku.test', 'ADMIN');
        $accountB = $this->createTestUser('usr_chat_b', 'Akun B', 'b@absenku.test', 'EMPLOYEE');
        $accountC = $this->createTestUser('usr_chat_c', 'Akun C', 'c@absenku.test', 'EMPLOYEE');

        Sanctum::actingAs($accountA);

        $createRes = $this->postJson('/api/chat/topics', [
            'id' => 'topic_shared_ab',
            'title' => 'Diskusi Proyek A dan B',
            'status' => 'DISCUSSING',
            'tagged_teammate_ids' => ['usr_chat_a', 'a@absenku.test', 'Akun A', 'usr_chat_b', 'b@absenku.test', 'Akun B'],
            'short_notes' => 'Catatan awal',
        ]);
        $createRes->assertOk();

        $msgRes = $this->postJson('/api/chat/messages', [
            'id' => 'msg_shared_1',
            'topic_id' => 'topic_shared_ab',
            'content' => 'Halo Akun B, cek file ini ya.',
        ]);
        $msgRes->assertOk();

        // Non-participant Account C cannot delete the topic.
        Sanctum::actingAs($accountC);
        $this->deleteJson('/api/chat/topics/topic_shared_ab')->assertStatus(403);

        // Account A deletes the topic -> hidden for A, preserved intact for B.
        Sanctum::actingAs($accountA);
        $delARes = $this->deleteJson('/api/chat/topics/topic_shared_ab');
        $delARes->assertOk();
        $delARes->assertJson([
            'id' => 'topic_shared_ab',
            'deleted_permanently' => false,
        ]);

        $topicsA = $this->getJson('/api/chat/topics')->assertOk()->json();
        $this->assertCount(0, $topicsA);

        // Account B still sees the topic and its messages.
        Sanctum::actingAs($accountB);
        $topicsB = $this->getJson('/api/chat/topics')->assertOk()->json();
        $this->assertCount(1, $topicsB);
        $this->assertSame('topic_shared_ab', $topicsB[0]['id']);
        $this->assertSame('usr_chat_a', $topicsB[0]['dibuat_oleh_id']);
        $this->assertCount(1, $topicsB[0]['messages']);
        $this->assertSame('Halo Akun B, cek file ini ya.', $topicsB[0]['messages'][0]['isi_pesan']);

        // When Account B also deletes it, no participants remain -> permanently deleted.
        $delBRes = $this->deleteJson('/api/chat/topics/topic_shared_ab');
        $delBRes->assertOk();
        $delBRes->assertJson([
            'id' => 'topic_shared_ab',
            'deleted_permanently' => true,
        ]);

        $this->assertNull(ChatTopic::find('topic_shared_ab'));
    }

    public function test_deleting_single_participant_topic_deletes_permanently_immediately(): void
    {
        $accountA = $this->createTestUser('usr_solo_a', 'Solo A', 'solo@absenku.test', 'EMPLOYEE');
        Sanctum::actingAs($accountA);

        $this->postJson('/api/chat/topics', [
            'id' => 'topic_solo_a',
            'title' => 'Catatan Pribadi',
            'status' => 'OPEN',
            'tagged_teammate_ids' => ['usr_solo_a'],
        ])->assertOk();

        $delRes = $this->deleteJson('/api/chat/topics/topic_solo_a');
        $delRes->assertOk();
        $delRes->assertJson([
            'id' => 'topic_solo_a',
            'deleted_permanently' => true,
        ]);

        $this->assertNull(ChatTopic::find('topic_solo_a'));
    }
}
