<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = AttendanceRecord::query();

        if (! in_array($user->peran, ['SUPER_ADMIN', 'ADMIN'])) {
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
        $today = date('Y-m-d');

        // Cegah presensi ganda di hari yang sama
        $existing = AttendanceRecord::where('pengguna_id', $user->id)
            ->where('tanggal', $today)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Anda sudah melakukan presensi hari ini.',
                'record' => $existing,
            ], 400);
        }

        $jamMasuk = $request->input('jam_masuk', $request->input('clock_in_time', date('H:i')));
        $lat = $request->input('latitude', $request->input('clock_in_lat'));
        $lng = $request->input('longitude', $request->input('clock_in_lng'));
        $jarak = $request->input('jarak_meter', $request->input('clock_in_distance_meter', 0));
        $statusLokasi = $request->input('status_lokasi', $request->input('clock_in_location_status', 'IN_OFFICE'));
        $statusHadir = $request->input('status_kehadiran', $request->input('clock_in_status', 'ON_TIME'));
        $menitTerlambat = $request->input('menit_keterlambatan', $request->input('clock_in_late_minutes', 0));
        $mode = $request->input('mode_kerja', $request->input('work_mode', 'WFO'));
        $catatan = $request->input('catatan', $request->input('notes'));

        $recordId = 'att_' . time() . '_' . Str::random(4);

        $record = AttendanceRecord::create([
            'id' => $recordId,
            'pengguna_id' => $user->id,
            'nama_karyawan' => $user->nama,
            'foto_karyawan' => $user->foto_profil,
            'divisi' => $user->divisi,
            'tipe_kepegawaian' => $user->tipe_kepegawaian,
            'tanggal' => $today,
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
        ]);

        return response()->json([
            'message' => 'Presensi berhasil dicatat.',
            'record' => $record,
        ], 201);
    }
}
