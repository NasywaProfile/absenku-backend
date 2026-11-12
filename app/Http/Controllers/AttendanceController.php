<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\OfficeConfig;
use App\Models\User;
use App\Support\InputSanitizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = AttendanceRecord::query();

        $role = strtoupper((string) ($user->peran ?? ''));
        if (! in_array($role, ['SUPER_ADMIN', 'ADMIN'], true)) {
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
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $record = AttendanceRecord::where('pengguna_id', $user->id)
            ->where('tanggal', $today)
            ->first();

        return response()->json($record);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));
        $isAdmin = in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);

        $requestedUserId = $request->input('user_id', $request->input('pengguna_id'));
        $isAdminEditingOther = false;

        if ($requestedUserId && $requestedUserId !== $user->id) {
            if (! $isAdmin) {
                return response()->json([
                    'message' => 'Operasi ditolak: Anda tidak berhak mencatat presensi atas nama pengguna lain.',
                ], 403);
            }
            $targetUser = User::find($requestedUserId);
            if (! $targetUser) {
                return response()->json(['message' => 'Pengguna target tidak ditemukan.'], 404);
            }
            if ($targetUser->id === 'usr_super_admin' || strtoupper((string) $targetUser->peran) === 'SUPER_ADMIN') {
                return response()->json([
                    'message' => 'Data presensi Super Administrator tidak dapat dimodifikasi.',
                ], 403);
            }
            $isAdminEditingOther = true;
        } else {
            $targetUser = $user;
        }

        $catatan = $request->input('catatan', $request->input('notes'));
        if ($catatan !== null) {
            $catatan = mb_substr(trim((string) $catatan), 0, 1000);
        }

        if ($isAdminEditingOther) {
            return $this->storeAdminEditedAttendance($request, $targetUser, $catatan);
        }

        return $this->storeSelfClockIn($request, $targetUser, $catatan);
    }

    /**
     * Presensi masuk mandiri karyawan: seluruh perhitungan jarak GPS, status geofence,
     * jam masuk WIB, dan status keterlambatan dihitung ulang secara otoritatif di server.
     */
    private function storeSelfClockIn(Request $request, User $targetUser, ?string $catatan)
    {
        $nowWib = Carbon::now('Asia/Jakarta');
        $today = $nowWib->toDateString();
        $jamMasuk = $nowWib->format('H:i:s');

        // Cegah manipulasi presensi ganda / menimpa catatan terlambat di hari yang sama
        $existingToday = AttendanceRecord::where('pengguna_id', $targetUser->id)
            ->where('tanggal', $today)
            ->first();

        if ($existingToday) {
            return response()->json([
                'message' => 'Presensi masuk untuk hari ini sudah tercatat dan tidak dapat diubah.',
                'record' => $existingToday,
            ], 409);
        }

        // Cek status izin/cuti yang disetujui pada hari ini di database server
        $approvedLeave = $this->resolveApprovedLeaveForDate($targetUser->id, $today);
        if ($approvedLeave && in_array(strtoupper((string) $approvedLeave->kategori_izin), ['SICK', 'LEAVE', 'ANNUAL'], true)) {
            return response()->json([
                'message' => 'Anda memiliki izin/cuti/sakit yang disetujui hari ini sehingga berstatus OFF.',
            ], 422);
        }

        // Validasi koordinat GPS wajib dikirim dengan rentang geografis sah
        $rawLat = $request->input('latitude', $request->input('clock_in_lat'));
        $rawLng = $request->input('longitude', $request->input('clock_in_lng'));

        if ($rawLat === null || $rawLng === null || ! is_numeric($rawLat) || ! is_numeric($rawLng)) {
            return response()->json([
                'message' => 'Koordinat GPS (latitude dan longitude) yang valid wajib dikirim.',
            ], 422);
        }

        $lat = (float) $rawLat;
        $lng = (float) $rawLng;

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return response()->json([
                'message' => 'Koordinat GPS berada di luar rentang geografis yang valid.',
            ], 422);
        }

        // Tolak koordinat kosong (0, 0 / Null Island) yang menandakan sinyal GPS belum aktif
        if (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
            return response()->json([
                'message' => 'Sinyal GPS belum valid (0, 0). Aktifkan izin lokasi perangkat Anda.',
            ], 422);
        }

        $office = $this->getAuthoritativeOfficeConfig();

        // Hitung ulang jarak Haversine langsung di server (mengabaikan input jarak_meter dari klien)
        $jarak = $this->calculateHaversineDistance(
            $lat,
            $lng,
            (float) $office->latitude,
            (float) $office->longitude
        );

        $isGeofenceActive = (bool) $office->status_geofence_aktif;
        $radiusLimit = (int) $office->radius_geofence_meter;
        $isWithinRadius = ! $isGeofenceActive || ($jarak <= $radiusLimit);

        // Tentukan status_lokasi dan mode_kerja otoritatif di server
        $approvedCat = $approvedLeave ? strtoupper((string) $approvedLeave->kategori_izin) : '';
        $isFreelance = strtoupper((string) ($targetUser->tipe_kepegawaian ?? '')) === 'FREELANCE';

        if ($approvedCat === 'BUSINESS_TRIP') {
            $statusLokasi = 'DINAS_APPROVED';
            $mode = 'DINAS';
        } elseif ($approvedCat === 'WFH') {
            $statusLokasi = 'WFH_APPROVED';
            $mode = 'WFH';
        } elseif ($isWithinRadius) {
            $statusLokasi = 'IN_OFFICE';
            $mode = 'WFO';
        } elseif ($isFreelance) {
            $statusLokasi = 'WFH_APPROVED';
            $mode = 'WFH';
        } else {
            $statusLokasi = 'OUT_OF_RANGE';
            $mode = 'WFO';
        }

        // Hitung ketepatan waktu dan menit keterlambatan secara otoritatif di server
        [$statusHadir, $menitTerlambat] = $this->calculatePunctuality(
            $nowWib,
            (string) ($office->jam_masuk ?: '08:00'),
            (int) ($office->toleransi_keterlambatan_menit ?? 0)
        );

        $record = AttendanceRecord::create([
            'id' => 'att_' . time() . '_' . Str::random(6),
            'pengguna_id' => $targetUser->id,
            'tanggal' => $today,
            'nama_karyawan' => $targetUser->nama,
            'foto_karyawan' => InputSanitizer::boundTextMediaUrl($targetUser->foto_profil)[1] ?? '',
            'divisi' => $targetUser->divisi ?: 'Operasional',
            'tipe_kepegawaian' => $targetUser->tipe_kepegawaian ?: 'CONTRACT',
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
        ], 200);
    }

    /**
     * Koreksi presensi karyawan lain oleh Admin / Super Admin (melalui EditAttendanceModal).
     */
    private function storeAdminEditedAttendance(Request $request, User $targetUser, ?string $catatan)
    {
        $today = $request->input('date', $request->input('tanggal', Carbon::now('Asia/Jakarta')->toDateString()));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $today)) {
            return response()->json(['message' => 'Format tanggal harus YYYY-MM-DD.'], 422);
        }

        $jamMasuk = (string) $request->input('jam_masuk', $request->input('clock_in_time', '08:00:00'));
        if (! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jamMasuk)) {
            return response()->json(['message' => 'Format jam masuk harus HH:mm atau HH:mm:ss.'], 422);
        }

        $statusLokasi = strtoupper((string) $request->input('status_lokasi', $request->input('clock_in_location_status', 'IN_OFFICE')));
        if (! in_array($statusLokasi, ['IN_OFFICE', 'OUT_OF_RANGE', 'WFH_APPROVED', 'DINAS_APPROVED'], true)) {
            return response()->json(['message' => 'Status lokasi tidak valid.'], 422);
        }

        $statusHadir = strtoupper((string) $request->input('status_kehadiran', $request->input('clock_in_status', 'ON_TIME')));
        if (! in_array($statusHadir, ['ON_TIME', 'LATE'], true)) {
            return response()->json(['message' => 'Status kehadiran tidak valid.'], 422);
        }

        $mode = strtoupper((string) $request->input('mode_kerja', $request->input('work_mode', 'WFO')));
        if (! in_array($mode, ['WFO', 'WFH', 'DINAS'], true)) {
            return response()->json(['message' => 'Mode kerja tidak valid.'], 422);
        }

        $menitTerlambat = $statusHadir === 'LATE'
            ? max(0, (int) $request->input('menit_keterlambatan', $request->input('clock_in_late_minutes', 0)))
            : 0;

        $office = $this->getAuthoritativeOfficeConfig();
        $rawLat = $request->input('latitude', $request->input('clock_in_lat', $office->latitude));
        $rawLng = $request->input('longitude', $request->input('clock_in_lng', $office->longitude));
        $lat = is_numeric($rawLat) ? (float) $rawLat : (float) $office->latitude;
        $lng = is_numeric($rawLng) ? (float) $rawLng : (float) $office->longitude;
        $jarak = $this->calculateHaversineDistance($lat, $lng, (float) $office->latitude, (float) $office->longitude);

        $existing = AttendanceRecord::where('pengguna_id', $targetUser->id)
            ->where('tanggal', $today)
            ->first();

        $recordId = $existing ? $existing->id : ('att_' . time() . '_' . Str::random(6));

        $record = AttendanceRecord::updateOrCreate(
            [
                'pengguna_id' => $targetUser->id,
                'tanggal' => $today,
            ],
            [
                'id' => $recordId,
                'nama_karyawan' => $targetUser->nama,
                'foto_karyawan' => InputSanitizer::boundTextMediaUrl($targetUser->foto_profil)[1] ?? '',
                'divisi' => $targetUser->divisi ?: 'Operasional',
                'tipe_kepegawaian' => $targetUser->tipe_kepegawaian ?: 'CONTRACT',
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
            'message' => 'Data presensi karyawan berhasil diperbarui.',
            'record' => $record,
        ], 200);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user->peran ?? ''));

        if (! in_array($role, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return response()->json([
                'message' => 'Operasi ditolak: Hanya Admin atau Super Admin yang dapat menghapus data presensi.',
            ], 403);
        }

        $date = $request->input('date', $request->input('tanggal', Carbon::now('Asia/Jakarta')->toDateString()));
        $targetUserId = $request->input('user_id', $request->input('pengguna_id'));

        if (! $targetUserId) {
            return response()->json(['message' => 'User ID diperlukan.'], 422);
        }

        // Proteksi integritas data: Admin dilarang menghapus catatan presensinya sendiri atau milik Super Admin
        if ($targetUserId === $user->id) {
            return response()->json([
                'message' => 'Operasi ditolak: Demi integritas data, Anda tidak dapat menghapus presensi milik sendiri.',
            ], 403);
        }

        if ($role !== 'SUPER_ADMIN') {
            $targetUser = User::find($targetUserId);
            if ($targetUser && ($targetUser->id === 'usr_super_admin' || strtoupper((string) $targetUser->peran) === 'SUPER_ADMIN')) {
                return response()->json([
                    'message' => 'Operasi ditolak: Data presensi Super Administrator tidak dapat dihapus.',
                ], 403);
            }
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            return response()->json(['message' => 'Format tanggal harus YYYY-MM-DD.'], 422);
        }

        AttendanceRecord::where('pengguna_id', $targetUserId)
            ->where('tanggal', $date)
            ->delete();

        return response()->json(['message' => 'Presensi berhasil dihapus.']);
    }

    private function getAuthoritativeOfficeConfig(): OfficeConfig
    {
        $office = OfficeConfig::first();
        if ($office) {
            return $office;
        }

        return new OfficeConfig([
            'nama_kantor' => 'IndigoSpace Malang (Kantor Pusat)',
            'latitude' => -7.94390000,
            'longitude' => 112.63220000,
            'radius_geofence_meter' => 100,
            'jam_masuk' => '08:00',
            'jam_pulang' => '17:00',
            'toleransi_keterlambatan_menit' => 0,
            'status_geofence_aktif' => true,
        ]);
    }

    private function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2)
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        return (int) round(self::EARTH_RADIUS_METERS * $c);
    }

    private function calculatePunctuality(Carbon $clockInWib, string $workStartHour, int $graceMinutes): array
    {
        $actualMinutes = ($clockInWib->hour * 60) + $clockInWib->minute;

        $parts = explode(':', $workStartHour ?: '08:00');
        $schedHour = (int) ($parts[0] ?? 8);
        $schedMinute = (int) ($parts[1] ?? 0);
        $scheduledMinutes = ($schedHour * 60) + $schedMinute;
        $cutoffMinutes = $scheduledMinutes + max(0, $graceMinutes);

        if ($actualMinutes <= $cutoffMinutes) {
            return ['ON_TIME', 0];
        }

        return ['LATE', max(1, $actualMinutes - $scheduledMinutes)];
    }

    private function resolveApprovedLeaveForDate(string $userId, string $date): ?LeaveRequest
    {
        $leaves = LeaveRequest::where('pengguna_id', $userId)
            ->where('status_pengajuan', 'APPROVED')
            ->where('tanggal_mulai', '<=', $date)
            ->where('tanggal_selesai', '>=', $date)
            ->get();

        if ($leaves->isEmpty()) {
            return null;
        }

        $priority = [
            'SICK' => 4,
            'LEAVE' => 3,
            'ANNUAL' => 3,
            'BUSINESS_TRIP' => 2,
            'WFH' => 1,
        ];

        return $leaves->sortByDesc(function ($leave) use ($priority) {
            return $priority[strtoupper((string) $leave->kategori_izin)] ?? 0;
        })->first();
    }
}
