<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatTopic;
use App\Models\User;
use App\Support\InputSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function getTopics(Request $request)
    {
        $user = $request->user();
        $topics = ChatTopic::with('messages')
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(fn (ChatTopic $topic) => ! $this->isHiddenForUser($topic, $user))
            ->values();

        return response()->json($topics);
    }

    public function storeTopic(Request $request)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));
        $isAdmin = in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);

        $id = trim((string) $request->input('id', 'tpc_' . time() . '_' . Str::random(6)));
        if ($id === '' || strlen($id) > 50 || ! preg_match('/^[a-zA-Z0-9._:-]+$/', $id)) {
            return response()->json(['message' => 'Format ID topik diskusi tidak valid.'], 422);
        }
        $existingTopic = ChatTopic::find($id);
        if ($existingTopic && ! $isAdmin && $existingTopic->dibuat_oleh_id !== $user->id) {
            return response()->json([
                'message' => 'Anda tidak berhak menimpa topik diskusi milik pengguna lain.',
            ], 403);
        }

        $judul = $request->input('judul_topik', $request->input('title', 'Topik Diskusi'));
        $status = $request->input('status_topik', $request->input('status', 'NEED_COMMENTS'));
        $tag = $request->input('id_anggota_ditandai', $request->input('taggedTeammateIds', $request->input('tagged_teammate_ids', [])));
        $berkas = $request->input('berkas', $request->input('files', []));
        $tautan = $request->input('tautan', $request->input('links', []));
        $gambar = $request->input('gambar', $request->input('images', []));

        if (InputSanitizer::findUnsafeMediaUrl($berkas) !== null
            || InputSanitizer::findUnsafeMediaUrl($tautan) !== null
            || InputSanitizer::findUnsafeMediaUrl($gambar) !== null
        ) {
            return response()->json([
                'message' => 'Lampiran atau tautan mengandung skema URL/tipe berkas berbahaya dan ditolak.',
            ], 422);
        }
        $catatanSingkat = $request->input('catatan_singkat', $request->input('shortNotes', $request->input('short_notes', $request->input('description', ''))));
        $catatanLengkap = $request->input('catatan_lengkap', $request->input('longNotes', $request->input('long_notes', '')));

        $topic = ChatTopic::updateOrCreate(
            ['id' => $id],
            [
                'judul_topik' => $judul,
                'status_topik' => $status,
                'id_anggota_ditandai' => $tag,
                'berkas' => $berkas,
                'tautan' => $tautan,
                'gambar' => $gambar,
                'catatan_singkat' => $catatanSingkat,
                'catatan_lengkap' => $catatanLengkap,
                'dibuat_oleh_id' => $existingTopic ? $existingTopic->dibuat_oleh_id : $user->id,
                'dibuat_oleh_nama' => $existingTopic ? $existingTopic->dibuat_oleh_nama : $user->nama,
                'dibuat_oleh_foto' => $existingTopic ? $existingTopic->dibuat_oleh_foto : (InputSanitizer::boundTextMediaUrl($user->foto_profil)[1] ?? ''),
            ]
        );

        return response()->json([
            'message' => 'Topik diskusi berhasil disimpan.',
            'topic' => $topic,
        ], 200);
    }

    public function updateTopic(Request $request, $id)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));
        $isAdmin = in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);

        $topic = ChatTopic::findOrFail($id);
        if (! $isAdmin && (! $this->isUserParticipantOfTopic($topic, $user) || $this->isHiddenForUser($topic, $user))) {
            return response()->json(['message' => 'Anda tidak berhak mengubah topik diskusi milik pengguna lain.'], 403);
        }

        $data = [];
        if ($request->has('judul_topik') || $request->has('title')) {
            $data['judul_topik'] = $request->input('judul_topik', $request->input('title'));
        }
        if ($request->has('status_topik') || $request->has('status')) {
            $data['status_topik'] = $request->input('status_topik', $request->input('status'));
        }
        if ($request->has('id_anggota_ditandai') || $request->has('taggedTeammateIds') || $request->has('tagged_teammate_ids')) {
            $nextTags = $request->input('id_anggota_ditandai', $request->input('taggedTeammateIds', $request->input('tagged_teammate_ids')));
            $nextTagsList = is_array($nextTags) ? array_values($nextTags) : [];
            $data['id_anggota_ditandai'] = $nextTagsList;

            // If an active user is re-tagged/invited back, remove them from hidden list so the room reappears.
            if ($this->hasHiddenByColumn() && ! empty($topic->dihapus_oleh_ids)) {
                $reinvitedUserIds = $this->resolveUserIdsMatchingTags($nextTagsList);
                if (! empty($reinvitedUserIds)) {
                    $data['dihapus_oleh_ids'] = collect($topic->dihapus_oleh_ids)
                        ->map(fn ($hiddenId) => (string) $hiddenId)
                        ->reject(fn ($hiddenId) => in_array($hiddenId, $reinvitedUserIds, true))
                        ->values()
                        ->all();
                }
            }
        }
        if ($request->has('berkas') || $request->has('files')) {
            $berkas = $request->input('berkas', $request->input('files'));
            if (InputSanitizer::findUnsafeMediaUrl($berkas) !== null) {
                return response()->json(['message' => 'Lampiran berkas mengandung skema URL/tipe tidak diizinkan.'], 422);
            }
            $data['berkas'] = $berkas;
        }
        if ($request->has('tautan') || $request->has('links')) {
            $tautan = $request->input('tautan', $request->input('links'));
            if (InputSanitizer::findUnsafeMediaUrl($tautan) !== null) {
                return response()->json(['message' => 'Tautan mengandung skema URL yang tidak diizinkan.'], 422);
            }
            $data['tautan'] = $tautan;
        }
        if ($request->has('gambar') || $request->has('images')) {
            $gambar = $request->input('gambar', $request->input('images'));
            if (InputSanitizer::findUnsafeMediaUrl($gambar) !== null) {
                return response()->json(['message' => 'Lampiran gambar mengandung skema URL/tipe tidak diizinkan.'], 422);
            }
            $data['gambar'] = $gambar;
        }
        if ($request->has('catatan_singkat') || $request->has('shortNotes') || $request->has('short_notes')) {
            $data['catatan_singkat'] = $request->input('catatan_singkat', $request->input('shortNotes', $request->input('short_notes')));
        }
        if ($request->has('catatan_lengkap') || $request->has('longNotes') || $request->has('long_notes')) {
            $data['catatan_lengkap'] = $request->input('catatan_lengkap', $request->input('longNotes', $request->input('long_notes')));
        }

        $topic->update($data);

        return response()->json([
            'message' => 'Topik diskusi berhasil diperbarui.',
            'topic' => $topic->fresh(),
        ]);
    }

    public function deleteTopic(Request $request, $id)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));
        $isAdmin = in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);

        $topic = ChatTopic::find($id);
        if (! $topic) {
            return response()->json([
                'message' => 'Topik diskusi berhasil dihapus.',
                'id' => $id,
                'deleted_permanently' => true,
            ]);
        }

        if (! $isAdmin && ! $this->isUserParticipantOfTopic($topic, $user)) {
            return response()->json(['message' => 'Anda tidak berhak menghapus topik diskusi milik pengguna lain.'], 403);
        }

        if ($this->hasRemainingParticipants($topic, $user)) {
            $userIdentifiers = $this->userIdentifiers($user);
            $remainingTags = collect($this->extractTaggedList($topic))
                ->filter(fn ($tag) => ! in_array(strtolower(trim((string) $tag)), $userIdentifiers, true))
                ->values()
                ->all();
            $topic->id_anggota_ditandai = $remainingTags;

            if ($this->hasHiddenByColumn()) {
                $hiddenIds = collect($topic->dihapus_oleh_ids ?? [])
                    ->map(fn ($item) => (string) $item)
                    ->push((string) $user->id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $topic->dihapus_oleh_ids = $hiddenIds;
            }
            $topic->save();

            return response()->json([
                'message' => 'Topik diskusi dihapus dari daftar Anda.',
                'id' => $id,
                'deleted_permanently' => false,
            ]);
        }

        $topic->delete();

        return response()->json([
            'message' => 'Topik diskusi berhasil dihapus.',
            'id' => $id,
            'deleted_permanently' => true,
        ]);
    }

    protected function hasHiddenByColumn(): bool
    {
        return Schema::hasColumn('topik_diskusi', 'dihapus_oleh_ids');
    }

    /**
     * @return list<string>
     */
    protected function extractTaggedList(ChatTopic $topic): array
    {
        $val = $topic->id_anggota_ditandai;
        if (is_array($val)) {
            return array_values($val);
        }
        $raw = $topic->getRawOriginal('id_anggota_ditandai');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values($decoded);
            }
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        return [];
    }

    /**
     * @param array<int, mixed> $tags
     * @return list<string>
     */
    protected function resolveUserIdsMatchingTags(array $tags): array
    {
        $normalizedTags = collect($tags)
            ->map(fn ($tag) => strtolower(trim((string) $tag)))
            ->filter()
            ->unique()
            ->all();

        if (empty($normalizedTags)) {
            return [];
        }

        $matchedIds = [];
        foreach (User::query()->select(['id', 'email', 'nama'])->get() as $candidate) {
            foreach ($this->userIdentifiers($candidate) as $identifier) {
                if (in_array($identifier, $normalizedTags, true)) {
                    $matchedIds[] = (string) $candidate->id;
                    break;
                }
            }
        }

        return array_values(array_unique($matchedIds));
    }

    /**
     * @return list<string>
     */
    protected function userIdentifiers(User $user): array
    {
        return array_values(array_unique(array_filter([
            strtolower(trim((string) $user->id)),
            strtolower(trim((string) $user->email)),
            strtolower(trim((string) $user->nama)),
        ])));
    }

    protected function isHiddenForUser(ChatTopic $topic, User $user): bool
    {
        $hiddenIds = collect($topic->dihapus_oleh_ids ?? [])
            ->map(fn ($item) => (string) $item)
            ->all();

        return in_array((string) $user->id, $hiddenIds, true);
    }

    protected function isUserParticipantOfTopic(ChatTopic $topic, User $user): bool
    {
        if ((string) $topic->dibuat_oleh_id === (string) $user->id) {
            return true;
        }

        $userIdentifiers = $this->userIdentifiers($user);
        foreach ($this->extractTaggedList($topic) as $tag) {
            if (in_array(strtolower(trim((string) $tag)), $userIdentifiers, true)) {
                return true;
            }
        }

        return false;
    }

    protected function hasRemainingParticipants(ChatTopic $topic, User $actingUser): bool
    {
        $hiddenIds = collect($topic->dihapus_oleh_ids ?? [])
            ->map(fn ($item) => (string) $item)
            ->push((string) $actingUser->id)
            ->filter()
            ->unique()
            ->all();

        $normalizedTags = collect($this->extractTaggedList($topic))
            ->map(fn ($tag) => strtolower(trim((string) $tag)))
            ->filter()
            ->unique()
            ->all();

        $creatorId = (string) ($topic->dibuat_oleh_id ?? '');

        $users = User::query()->select(['id', 'email', 'nama'])->get();
        foreach ($users as $candidate) {
            if (in_array((string) $candidate->id, $hiddenIds, true)) {
                continue;
            }

            if ($creatorId !== '' && $creatorId === (string) $candidate->id) {
                return true;
            }

            foreach ($this->userIdentifiers($candidate) as $identifier) {
                if (in_array($identifier, $normalizedTags, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getMessages(Request $request)
    {
        $query = ChatMessage::query();

        if ($request->has('topic_id') || $request->has('topik_id')) {
            $targetTopicId = $request->input('topik_id', $request->input('topic_id'));
            $query->where('topik_id', $targetTopicId);
        }

        if ($request->has('channel_id') || $request->has('kanal')) {
            $targetChannel = $request->input('kanal', $request->input('channel_id'));
            $query->where('kanal', $targetChannel);
        }

        $messages = $query->orderBy('created_at', 'asc')->get();

        return response()->json($messages);
    }

    public function sendMessage(Request $request)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));
        $isAdmin = in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);

        $isi = $request->input('isi_pesan', $request->input('content', $request->input('notes', '')));
        $topikId = $request->input('topik_id', $request->input('topic_id'));
        $kanal = $request->input('kanal', $request->input('channel_id', 'general'));
        $lampiran = $request->input('lampiran', $request->input('attachments', []));

        if ($topikId !== null && $topikId !== '') {
            $topikId = trim((string) $topikId);
            if (strlen($topikId) > 50 || ! preg_match('/^[a-zA-Z0-9._:-]+$/', $topikId)) {
                return response()->json(['message' => 'Format topik_id tidak valid.'], 422);
            }
        }

        if (InputSanitizer::findUnsafeMediaUrl($lampiran) !== null) {
            return response()->json(['message' => 'Lampiran pesan mengandung skema URL/tipe tidak diizinkan.'], 422);
        }

        $msgId = trim((string) $request->input('id', 'msg_' . time() . '_' . Str::random(6)));
        if ($msgId === '' || strlen($msgId) > 50 || ! preg_match('/^[a-zA-Z0-9._:-]+$/', $msgId)) {
            return response()->json(['message' => 'Format ID pesan tidak valid.'], 422);
        }
        $existingMsg = ChatMessage::find($msgId);
        if ($existingMsg && ! $isAdmin && $existingMsg->pengguna_id !== $user->id) {
            return response()->json([
                'message' => 'Anda tidak berhak menimpa pesan milik pengguna lain.',
            ], 403);
        }

        try {
            $penggunaId = $user->id;

            // Pastikan topik_id ada di database agar tidak gagal foreign key
            if ($topikId) {
                $topicExists = \App\Models\ChatTopic::where('id', $topikId)->exists();
                if (! $topicExists) {
                    \App\Models\ChatTopic::create([
                        'id' => $topikId,
                        'judul_topik' => 'Topik Obrolan',
                        'dibuat_oleh_id' => $penggunaId,
                        'dibuat_oleh_nama' => $user->nama,
                        'status_topik' => 'NEED_COMMENTS',
                    ]);
                }
            }

            $message = ChatMessage::updateOrCreate(
                ['id' => $msgId],
                [
                    'topik_id' => $topikId,
                    'pengguna_id' => $existingMsg ? $existingMsg->pengguna_id : $penggunaId,
                    'nama_pengirim' => $existingMsg ? $existingMsg->nama_pengirim : $user->nama,
                    'foto_pengirim' => $existingMsg ? $existingMsg->foto_pengirim : InputSanitizer::boundTextMediaUrl($user->foto_profil)[1],
                    'peran_pengirim' => $existingMsg ? $existingMsg->peran_pengirim : $user->peran,
                    'tipe_kepegawaian' => $existingMsg ? $existingMsg->tipe_kepegawaian : ($user->tipe_kepegawaian ?: 'CONTRACT'),
                    'isi_pesan' => $isi,
                    'waktu_kirim' => $existingMsg ? $existingMsg->waktu_kirim : now('Asia/Jakarta')->format('H:i'),
                    'kanal' => $kanal,
                    'lampiran' => $lampiran,
                ]
            );

            return response()->json([
                'message' => 'Pesan terkirim.',
                'chat_message' => $message,
            ], 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[ChatController::sendMessage] ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menyimpan pesan diskusi.',
            ], 500);
        }
    }

    public function deleteMessage(Request $request, $id)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));
        $isAdmin = in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);

        $msg = ChatMessage::find($id);
        if ($msg) {
            if (! $isAdmin && $msg->pengguna_id !== $user->id) {
                return response()->json(['message' => 'Anda tidak berhak menghapus pesan milik pengguna lain.'], 403);
            }
            $msg->delete();
        }

        return response()->json([
            'message' => 'Pesan diskusi berhasil dihapus.',
            'id' => $id,
        ]);
    }
}
