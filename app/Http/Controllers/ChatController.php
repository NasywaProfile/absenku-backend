<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatTopic;
use App\Support\InputSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function getTopics(Request $request)
    {
        $topics = ChatTopic::with('messages')
            ->orderBy('created_at', 'desc')
            ->get();

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
                'dibuat_oleh_foto' => $existingTopic ? $existingTopic->dibuat_oleh_foto : ($user->foto_profil ?? ''),
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
        if (! $isAdmin && $topic->dibuat_oleh_id !== $user->id) {
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
            $data['id_anggota_ditandai'] = $request->input('id_anggota_ditandai', $request->input('taggedTeammateIds', $request->input('tagged_teammate_ids')));
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
        if ($topic) {
            if (! $isAdmin && $topic->dibuat_oleh_id !== $user->id) {
                return response()->json(['message' => 'Anda tidak berhak menghapus topik diskusi milik pengguna lain.'], 403);
            }
            $topic->delete();
        }

        return response()->json([
            'message' => 'Topik diskusi berhasil dihapus.',
            'id' => $id,
        ]);
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
                    'foto_pengirim' => $existingMsg ? $existingMsg->foto_pengirim : $user->foto_profil,
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
