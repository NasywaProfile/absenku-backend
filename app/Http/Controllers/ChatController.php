<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatTopic;
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

        $id = $request->input('id', 'tpc_' . time() . '_' . Str::random(4));
        $judul = $request->input('judul_topik', $request->input('title', 'Topik Diskusi'));
        $status = $request->input('status_topik', $request->input('status', 'NEED_COMMENTS'));
        $tag = $request->input('id_anggota_ditandai', $request->input('taggedTeammateIds', $request->input('tagged_teammate_ids', [])));
        $berkas = $request->input('berkas', $request->input('files', []));
        $tautan = $request->input('tautan', $request->input('links', []));
        $gambar = $request->input('gambar', $request->input('images', []));
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
                'dibuat_oleh_id' => $user->id,
                'dibuat_oleh_nama' => $user->nama,
                'dibuat_oleh_foto' => $user->foto_profil ?? '',
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
            $data['berkas'] = $request->input('berkas', $request->input('files'));
        }
        if ($request->has('tautan') || $request->has('links')) {
            $data['tautan'] = $request->input('tautan', $request->input('links'));
        }
        if ($request->has('gambar') || $request->has('images')) {
            $data['gambar'] = $request->input('gambar', $request->input('images'));
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

        $isi = $request->input('isi_pesan', $request->input('content', $request->input('notes', '')));
        $topikId = $request->input('topik_id', $request->input('topic_id'));
        $kanal = $request->input('kanal', $request->input('channel_id', 'general'));
        $lampiran = $request->input('lampiran', $request->input('attachments', []));

        $msgId = $request->input('id', 'msg_' . time() . '_' . Str::random(4));

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
                    'pengguna_id' => $penggunaId,
                    'nama_pengirim' => $user->nama,
                    'foto_pengirim' => $user->foto_profil,
                    'peran_pengirim' => $user->peran,
                    'tipe_kepegawaian' => $user->tipe_kepegawaian ?: 'CONTRACT',
                    'isi_pesan' => $isi,
                    'waktu_kirim' => date('H:i'),
                    'kanal' => $kanal,
                    'lampiran' => $lampiran,
                ]
            );

            return response()->json([
                'message' => 'Pesan terkirim.',
                'chat_message' => $message,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Pesan dicatat: ' . $e->getMessage(),
            ], 200);
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
