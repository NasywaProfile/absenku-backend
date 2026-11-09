<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = AttendanceRecord::query();

        $role = strtoupper($user->peran ?? '');
        if (! in_array($role, ['SUPER_ADMIN', 'ADMIN'])) {
            $query->where('pengguna_id', $user->id);
        } elseif ($request->has('user_id') || $request->has('pengguna_id')) {
            $targetId = $request->input('user_id', $request->input('pengguna_id'));
            $query->where('pengguna_id', $targetId);
        }

        if ($request->has('date') || $request->has('tanggal')) {
            $targetDate = $request->input('date', $request->input('tanggal'));
            $query->where('tanggal', $targetDate);
        }

        $records = $query->orderBy('tanggal', 'desc')->orderBy('jam_masuk', 'desc')->get();

        return response()->json($records);
    }

    public function today(Request $request)
    {
        $user = $request->user();
        $today = date('Y-m-d');

        $record = AttendanceRecord::where('pengguna_id', $user->id)
            ->where('tanggal', $today)
            ->first();

        return response()->json($record);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $role = strtoupper($user->peran ?? '');
        $today = $request->input('date', $request->input('tanggal', date('Y-m-d')));

        $targetUserId = ($user && in_array($role, ['SUPER_ADMIN', 'ADMIN']) && ($request->has('user_id') || $request->has('pengguna_id')))
            ? $request->input('user_id', $request->input('pengguna_id'))
            : $user->id;

        $targetUser = $targetUserId === $user->id ? $user : User::find($targetUserId);
        $namaKaryawan = $targetUser ? $targetUser->nama : $user->nama;
        $fotoKaryawan = $targetUser ? $targetUser->foto_profil : $user->foto_profil;
        $divisi = $targetUser ? $targetUser->divisi : $user->divisi;
        $tipe = $targetUser ? $targetUser->tipe_kepegawaian : $user->tipe_kepegawaian;

        $jamMasuk = $request->input('jam_masuk', $request->input('clock_in_time', date('H:i')));
        $lat = $request->input('latitude', $request->input('clock_in_lat'));
        $lng = $request->input('longitude', $request->input('clock_in_lng'));
        $jarak = $request->input('jarak_meter', $request->input('clock_in_distance_meter', 0));
        $statusLokasi = $request->input('status_lokasi', $request->input('clock_in_location_status', 'IN_OFFICE'));
        $statusHadir = $request->input('status_kehadiran', $request->input('clock_in_status', 'ON_TIME'));
        $menitTerlambat = $request->input('menit_keterlambatan', $request->input('clock_in_late_minutes', 0));
        $mode = $request->input('mode_kerja', $request->input('work_mode', 'WFO'));
        $catatan = $request->input('catatan', $request->input('notes'));

        $recordId = $request->input('id', 'att_' . time() . '_' . Str::random(4));

        $record = AttendanceRecord::updateOrCreate(
            [
                'pengguna_id' => $targetUserId,
                'tanggal' => $today,
            ],
            [
                'id' => $recordId,
                'nama_karyawan' => $namaKaryawan,
                'foto_karyawan' => $fotoKaryawan,
                'divisi' => $divisi,
                'tipe_kepegawaian' => $tipe,
                'jam_masuk' => $jamMasuk,
                'latitude' => $lat,
                'longitude' => $lng,
                'jarak_meter' => $jarak,
                'status_lokasi' => $statusLokasi,
                'status_kehadiran' => $statusHadir,
                'menit_keterlambatan' => $menitTerlambat,
                'mode_kerja' => $mode,
                'catatan' => $catatan,
                'status_sinkronisasi' => 'SYNCED',
            ]
        );

        return response()->json([
            'message' => 'Presensi berhasil dicatat.',
            'record' => $record,
        ], 200);
    }
}
