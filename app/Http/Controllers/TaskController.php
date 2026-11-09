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
        $user = $request->user();
        $query = DailyTaskLog::query();

        if ($request->has('user_id') || $request->has('pengguna_id')) {
            $targetId = $request->input('user_id', $request->input('pengguna_id'));
            $query->where('pengguna_id', $targetId);
        } else {
            $query->where('pengguna_id', $user->id);
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

        $tanggal = $request->input('tanggal', $request->input('date', date('Y-m-d')));
        $tugas = $request->input('daftar_tugas', $request->input('tasks', []));
        $catatan = $request->input('catatan', $request->input('notes', ''));

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

        $judul = $request->input('judul_tugas', $request->input('title'));
        $deskripsi = $request->input('deskripsi', $request->input('description', ''));
        $status = $request->input('status_tugas', $request->input('status', 'TODO'));
        $prioritas = $request->input('prioritas', $request->input('priority', 'MEDIUM'));
        $ditugaskan = $request->input('ditugaskan_ke', $request->input('assigned_to'));
        $petugasNama = $request->input('nama_petugas', $request->input('assigned_name'));
        $petugasFoto = $request->input('foto_petugas', $request->input('assigned_avatar'));
        $anggota = $request->input('daftar_anggota', $request->input('assignees', []));
        $tenggat = $request->input('tenggat_waktu', $request->input('due_date'));
        $lampiran = $request->input('lampiran', $request->input('attachments', []));

        $taskId = $request->input('id', 'tsk_' . time() . '_' . Str::random(4));

        $creatorName = $user ? $user->nama : ($request->input('created_by_name', $request->input('created_by', 'Administrator')));

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
                'dibuat_oleh' => $creatorName,
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
        $task = KanbanTask::findOrFail($id);

        $data = [];
        if ($request->has('judul_tugas') || $request->has('title')) {
            $data['judul_tugas'] = $request->input('judul_tugas', $request->input('title'));
        }
        if ($request->has('deskripsi') || $request->has('description')) {
            $data['deskripsi'] = $request->input('deskripsi', $request->input('description'));
        }
        if ($request->has('status_tugas') || $request->has('status')) {
            $data['status_tugas'] = $request->input('status_tugas', $request->input('status'));
        }
        if ($request->has('prioritas') || $request->has('priority')) {
            $data['prioritas'] = $request->input('prioritas', $request->input('priority'));
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
        if ($request->has('lampiran') || $request->has('attachments')) {
            $data['lampiran'] = $request->input('lampiran', $request->input('attachments'));
        }

        $task->update($data);

        return response()->json([
            'message' => 'Tugas proyek berhasil diperbarui.',
            'task' => $task->fresh(),
        ]);
    }

    public function deleteKanbanTask(Request $request, $id)
    {
        $task = KanbanTask::find($id);
        if ($task) {
            $task->delete();
        }

        return response()->json([
            'message' => 'Tugas proyek berhasil dihapus.',
        ], 200);
    }
}
