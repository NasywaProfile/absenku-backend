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

    /**
     * Menormalisasi input tenggat waktu menjadi format Y-m-d atau null agar aman di strict MySQL DATE.
     */
    private function normalizeDueDate(mixed $raw): array
    {
        if ($raw === null) {
            return [true, null];
        }

        $trimmed = trim((string) $raw);
        if ($trimmed === '') {
            return [true, null];
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $trimmed, $matches) === 1) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $matches[1]);
            $errors = \DateTimeImmutable::getLastErrors();
            $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
            if ($parsed !== false && ! $hasErrors) {
                return [true, $matches[1]];
            }
        }

        return [false, null];
    }

    /**
     * Menjaga batas panjang kolom string VARCHAR MySQL agar tidak memicu SQLSTATE[22001].
     */
    private function truncateStr(?string $value, int $maxLen): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr(trim($value), 0, $maxLen);
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

        $judul = trim((string) ($request->input('judul_tugas', $request->input('title', '')) ?? ''));
        if ($judul === '') {
            return response()->json(['message' => 'Judul tugas wajib diisi.'], 422);
        }
        $deskripsi = (string) ($request->input('deskripsi', $request->input('description', '')) ?? '');
        $status = strtoupper((string) ($request->input('status_tugas', $request->input('status', 'TODO')) ?? 'TODO'));
        if (! in_array($status, ['TODO', 'IN_PROGRESS', 'DONE', 'REVIEWED', 'REVISION'], true)) {
            $status = 'TODO';
        }
        $prioritas = strtoupper((string) ($request->input('prioritas', $request->input('priority', 'MEDIUM')) ?? 'MEDIUM'));
        if (! in_array($prioritas, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true)) {
            $prioritas = 'MEDIUM';
        }
        $ditugaskanRaw = $request->input('ditugaskan_ke', $request->input('assigned_to'));
        $ditugaskan = $ditugaskanRaw !== null ? $this->truncateStr((string) $ditugaskanRaw, 50) : null;
        $petugasNamaRaw = $request->input('nama_petugas', $request->input('assigned_name'));
        $petugasNama = $petugasNamaRaw !== null ? $this->truncateStr((string) $petugasNamaRaw, 255) : null;
        $petugasFoto = $request->input('foto_petugas', $request->input('assigned_avatar'));
        $anggota = $request->input('daftar_anggota', $request->input('assignees', []));
        if (! is_array($anggota)) {
            $anggota = [];
        }

        [$validDueDate, $tenggat] = $this->normalizeDueDate(
            $request->input('tenggat_waktu', $request->input('due_date'))
        );
        if (! $validDueDate) {
            return response()->json([
                'message' => 'Format tenggat waktu tidak valid (gunakan YYYY-MM-DD).',
            ], 422);
        }

        [$validAvatar, $normalizedAvatar] = $this->normalizeAssigneeAvatar($petugasFoto);
        $lampiran = $request->input('lampiran', $request->input('attachments', []));
        if (! is_array($lampiran)) {
            $lampiran = [];
        }
        if (\App\Support\InputSanitizer::findUnsafeMediaUrl($lampiran) !== null || ! $validAvatar) {
            return response()->json([
                'message' => 'Lampiran tugas mengandung skema URL atau tipe berkas yang tidak diizinkan.',
            ], 422);
        }

        $taskId = trim((string) ($request->input('id') ?? ''));
        if ($taskId === '') {
            $taskId = 'tsk_' . time() . '_' . Str::random(4);
        } elseif (strlen($taskId) > 50 || preg_match('/^[a-zA-Z0-9._:-]+$/', $taskId) !== 1) {
            return response()->json(['message' => 'Format ID tugas tidak valid.'], 422);
        }

        $existingTask = KanbanTask::find($taskId);

        $task = KanbanTask::updateOrCreate(
            ['id' => $taskId],
            [
                'judul_tugas' => $this->truncateStr($judul, 255) ?? $judul,
                'deskripsi' => $deskripsi,
                'status_tugas' => $status,
                'prioritas' => $prioritas,
                'ditugaskan_ke' => $ditugaskan,
                'nama_petugas' => $petugasNama,
                'foto_petugas' => $normalizedAvatar,
                'daftar_anggota' => $anggota,
                'dibuat_oleh' => $existingTask && $existingTask->dibuat_oleh
                    ? $existingTask->dibuat_oleh
                    : $this->truncateStr((string) $user->id, 100),
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
            $isAssigned = ($task->ditugaskan_ke === $user->id)
                || in_array($user->id, $assignees, true)
                || collect($assignees)->contains(fn ($a) => is_array($a) && ($a['id'] ?? null) === $user->id);
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
            if (! is_array($lampiranUpdate)) {
                $lampiranUpdate = [];
            }
            if (\App\Support\InputSanitizer::findUnsafeMediaUrl($lampiranUpdate) !== null) {
                return response()->json([
                    'message' => 'Lampiran tugas mengandung skema URL atau tipe berkas yang tidak diizinkan.',
                ], 422);
            }
            $data['lampiran'] = $lampiranUpdate;
        }

        if ($isAdmin) {
            if ($request->has('deskripsi') || $request->has('description')) {
                $data['deskripsi'] = (string) ($request->input('deskripsi', $request->input('description')) ?? '');
            }
            if ($request->has('judul_tugas') || $request->has('title')) {
                $judulUpdate = trim((string) ($request->input('judul_tugas', $request->input('title')) ?? ''));
                if ($judulUpdate === '') {
                    return response()->json(['message' => 'Judul tugas tidak boleh kosong.'], 422);
                }
                $data['judul_tugas'] = $this->truncateStr($judulUpdate, 255);
            }
            if ($request->has('prioritas') || $request->has('priority')) {
                $reqPrio = strtoupper((string) $request->input('prioritas', $request->input('priority')));
                if (in_array($reqPrio, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true)) {
                    $data['prioritas'] = $reqPrio;
                }
            }
            if ($request->has('ditugaskan_ke') || $request->has('assigned_to')) {
                $rawAssignee = $request->input('ditugaskan_ke', $request->input('assigned_to'));
                $data['ditugaskan_ke'] = $rawAssignee !== null ? $this->truncateStr((string) $rawAssignee, 50) : null;
            }
            if ($request->has('nama_petugas') || $request->has('assigned_name')) {
                $rawName = $request->input('nama_petugas', $request->input('assigned_name'));
                $data['nama_petugas'] = $rawName !== null ? $this->truncateStr((string) $rawName, 255) : null;
            }
            if ($request->has('foto_petugas') || $request->has('assigned_avatar')) {
                [$validAvatar, $normalizedAvatar] = $this->normalizeAssigneeAvatar(
                    $request->input('foto_petugas', $request->input('assigned_avatar'))
                );
                if (! $validAvatar) {
                    return response()->json([
                        'message' => 'URL foto petugas tidak valid atau tidak diizinkan.',
                    ], 422);
                }
                $data['foto_petugas'] = $normalizedAvatar;
            }
            if ($request->has('daftar_anggota') || $request->has('assignees')) {
                $anggotaUpdate = $request->input('daftar_anggota', $request->input('assignees'));
                $data['daftar_anggota'] = is_array($anggotaUpdate) ? $anggotaUpdate : [];
            }
            if ($request->has('tenggat_waktu') || $request->has('due_date')) {
                [$validDue, $tenggatUpdate] = $this->normalizeDueDate(
                    $request->input('tenggat_waktu', $request->input('due_date'))
                );
                if (! $validDue) {
                    return response()->json([
                        'message' => 'Format tenggat waktu tidak valid (gunakan YYYY-MM-DD).',
                    ], 422);
                }
                $data['tenggat_waktu'] = $tenggatUpdate;
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

    /**
     * Validate assignee avatar URL/data-URI and bound it to MySQL TEXT capacity (65,535 bytes).
     *
     * @return array{0: bool, 1: ?string}
     */
    private function normalizeAssigneeAvatar(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [true, null];
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [true, null];
        }
        if (! \App\Support\InputSanitizer::isSafeMediaUrl($trimmed)) {
            return [false, null];
        }
        if (strlen($trimmed) > 65000) {
            return [true, null];
        }

        return [true, $trimmed];
    }
}
