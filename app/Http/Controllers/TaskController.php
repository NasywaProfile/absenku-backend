<?php

namespace App\Http\Controllers;

use App\Models\DailyTaskLog;
use App\Models\KanbanTask;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    // ================= Logbook Harian =================
    public function getDailyLogs(Request $request)
    {
        $query = DailyTaskLog::query();

        // Jika ada filter spesifik user_id, tampilkan milik user tersebut
        // Jika tidak ada, kembalikan semua logbook harian agar semua akun dapat saling melihat
        if ($request->has('user_id') || $request->has('pengguna_id')) {
            $targetId = $request->input('user_id', $request->input('pengguna_id'));
            $query->where('pengguna_id', $targetId);
        }

        if ($request->has('date') || $request->has('tanggal')) {
            $targetDate = $request->input('date', $request->input('tanggal'));
            $query->where('tanggal', $targetDate);
        }

        $logs = $query->orderBy('tanggal', 'desc')->get();
        return response()->json($logs);
    }

    public function saveDailyLog(Request $request)
    {
        $user = $request->user();

        $tanggal = (string) $request->input('tanggal', $request->input('date', now('Asia/Jakarta')->toDateString()));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return response()->json(['message' => 'Format tanggal logbook harus YYYY-MM-DD.'], 422);
        }
        $tugas = $request->input('daftar_tugas', $request->input('tasks', []));
        if (! is_array($tugas)) {
            $tugas = [];
        }
        $tugas = array_slice($tugas, 0, 50);
        $catatan = mb_substr(trim((string) $request->input('catatan', $request->input('notes', ''))), 0, 5000);

        $log = DailyTaskLog::updateOrCreate(
            [
                'pengguna_id' => $user->id,
                'tanggal' => $tanggal,
            ],
            [
                'nama_karyawan' => $user->nama,
                'daftar_tugas' => $tugas,
                'catatan' => $catatan,
            ]
        );

        return response()->json([
            'message' => 'Laporan tugas harian berhasil disimpan.',
            'log' => $log,
        ]);
    }

    // ================= Tugas Proyek (Kanban) =================
    public function getKanbanTasks(Request $request)
    {
        $tasks = KanbanTask::orderBy('created_at', 'desc')->get();
        return response()->json($tasks);
    }

    public function storeKanbanTask(Request $request)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));
        if (! $user || ! in_array($role, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return response()->json([
                'message' => 'Unauthorized. Hanya Admin atau Super Admin yang dapat membuat tugas proyek.',
            ], 403);
        }

        $judul = trim((string) $request->input('judul_tugas', $request->input('title', '')));
        if ($judul === '') {
            return response()->json(['message' => 'Judul tugas wajib diisi.'], 422);
        }
        $deskripsi = $request->input('deskripsi', $request->input('description', ''));
        $status = strtoupper((string) $request->input('status_tugas', $request->input('status', 'TODO')));
        if (! in_array($status, ['TODO', 'IN_PROGRESS', 'DONE', 'REVIEWED', 'REVISION'], true)) {
            $status = 'TODO';
        }
        $prioritas = strtoupper((string) $request->input('prioritas', $request->input('priority', 'MEDIUM')));
        if (! in_array($prioritas, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true)) {
            $prioritas = 'MEDIUM';
        }
        $ditugaskan = $request->input('ditugaskan_ke', $request->input('assigned_to'));
        $petugasNama = $request->input('nama_petugas', $request->input('assigned_name'));
        $petugasFoto = $request->input('foto_petugas', $request->input('assigned_avatar'));
        $anggota = $request->input('daftar_anggota', $request->input('assignees', []));
        $tenggat = $request->input('tenggat_waktu', $request->input('due_date'));
        $lampiran = $request->input('lampiran', $request->input('attachments', []));
        if (\App\Support\InputSanitizer::findUnsafeMediaUrl($lampiran) !== null
            || (is_string($petugasFoto) && ! \App\Support\InputSanitizer::isSafeMediaUrl($petugasFoto))
        ) {
            return response()->json([
                'message' => 'Lampiran tugas mengandung skema URL atau tipe berkas yang tidak diizinkan.',
            ], 422);
        }

        $taskId = $request->input('id', 'tsk_' . time() . '_' . Str::random(4));

        $task = KanbanTask::updateOrCreate(
            ['id' => $taskId],
            [
                'judul_tugas' => $judul,
                'deskripsi' => $deskripsi,
                'status_tugas' => $status,
                'prioritas' => $prioritas,
                'ditugaskan_ke' => $ditugaskan,
                'nama_petugas' => $petugasNama,
                'foto_petugas' => $petugasFoto,
                'daftar_anggota' => $anggota,
                'dibuat_oleh' => $user->nama,
                'tenggat_waktu' => $tenggat,
                'lampiran' => $lampiran,
            ]
        );

        return response()->json([
            'message' => 'Tugas proyek berhasil dibuat.',
            'task' => $task,
        ], 201);
    }

    public function updateKanbanTask(Request $request, $id)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));
        $isAdmin = in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);

        $task = KanbanTask::findOrFail($id);

        if (! $isAdmin) {
            $assignees = is_array($task->daftar_anggota) ? $task->daftar_anggota : [];
            $isAssigned = ($task->ditugaskan_ke === $user->id) || in_array($user->id, $assignees, true);
            if (! $isAssigned) {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk mengubah tugas ini.',
                ], 403);
            }
        }

        $data = [];
        if ($request->has('status_tugas') || $request->has('status')) {
            $reqStatus = strtoupper((string) $request->input('status_tugas', $request->input('status')));
            $validStatuses = ['TODO', 'IN_PROGRESS', 'DONE', 'REVIEWED', 'REVISION'];
            if (! in_array($reqStatus, $validStatuses, true)) {
                return response()->json(['message' => 'Status tugas tidak valid.'], 422);
            }
            // Karyawan non-Admin tidak berhak menyetujui/ACC tugas sendiri (REVIEWED / REVISION)
            if (! $isAdmin && in_array($reqStatus, ['REVIEWED', 'REVISION'], true)) {
                return response()->json([
                    'message' => 'Hanya Admin atau Super Admin yang dapat menyetujui atau meminta revisi tugas.',
                ], 403);
            }
            $data['status_tugas'] = $reqStatus;
        }
        if ($request->has('lampiran') || $request->has('attachments')) {
            $lampiranUpdate = $request->input('lampiran', $request->input('attachments'));
            if (\App\Support\InputSanitizer::findUnsafeMediaUrl($lampiranUpdate) !== null) {
                return response()->json([
                    'message' => 'Lampiran tugas mengandung skema URL atau tipe berkas yang tidak diizinkan.',
                ], 422);
            }
            $data['lampiran'] = $lampiranUpdate;
        }

        if ($isAdmin) {
            if ($request->has('deskripsi') || $request->has('description')) {
                $data['deskripsi'] = $request->input('deskripsi', $request->input('description'));
            }
            if ($request->has('judul_tugas') || $request->has('title')) {
                $data['judul_tugas'] = $request->input('judul_tugas', $request->input('title'));
            }
            if ($request->has('prioritas') || $request->has('priority')) {
                $reqPrio = strtoupper((string) $request->input('prioritas', $request->input('priority')));
                if (in_array($reqPrio, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true)) {
                    $data['prioritas'] = $reqPrio;
                }
            }
            if ($request->has('ditugaskan_ke') || $request->has('assigned_to')) {
                $data['ditugaskan_ke'] = $request->input('ditugaskan_ke', $request->input('assigned_to'));
            }
            if ($request->has('nama_petugas') || $request->has('assigned_name')) {
                $data['nama_petugas'] = $request->input('nama_petugas', $request->input('assigned_name'));
            }
            if ($request->has('foto_petugas') || $request->has('assigned_avatar')) {
                $data['foto_petugas'] = $request->input('foto_petugas', $request->input('assigned_avatar'));
            }
            if ($request->has('daftar_anggota') || $request->has('assignees')) {
                $data['daftar_anggota'] = $request->input('daftar_anggota', $request->input('assignees'));
            }
            if ($request->has('tenggat_waktu') || $request->has('due_date')) {
                $data['tenggat_waktu'] = $request->input('tenggat_waktu', $request->input('due_date'));
            }
        }

        $task->update($data);

        return response()->json([
            'message' => 'Tugas proyek berhasil diperbarui.',
            'task' => $task->fresh(),
        ]);
    }

    public function deleteKanbanTask(Request $request, $id)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));
        if (! $user || ! in_array($role, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return response()->json([
                'message' => 'Unauthorized. Hanya Admin atau Super Admin yang dapat menghapus tugas proyek.',
            ], 403);
        }

        $task = KanbanTask::find($id);
        if ($task) {
            $task->delete();
        }

        return response()->json([
            'message' => 'Tugas proyek berhasil dihapus.',
        ], 200);
    }
}
