<?php

namespace App\Http\Controllers;

use App\Models\OfficeConfig;
use Illuminate\Http\Request;

class OfficeConfigController extends Controller
{
    public function show()
    {
        $config = OfficeConfig::first();

        if (! $config) {
            $config = OfficeConfig::create([
                'nama_kantor' => 'IndigoSpace Malang (Kantor Pusat)',
                'alamat_kantor' => 'Jl. Simpang Borobudur No. 1, Mojolangu, Kec. Lowokwaru, Kota Malang, Jawa Timur 65142',
                'latitude' => -7.94390000,
                'longitude' => 112.63220000,
                'radius_geofence_meter' => 100,
                'jam_masuk' => '08:00',
                'jam_pulang' => '17:00',
                'toleransi_keterlambatan_menit' => 0,
                'status_geofence_aktif' => true,
            ]);
        }

        return response()->json($config);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $role = strtoupper((string) ($user?->peran ?? ''));

        if ($role !== 'SUPER_ADMIN') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengubah konfigurasi kantor.'], 403);
        }

        $validated = $request->validate([
            'nama_kantor' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'alamat_kantor' => 'nullable|string|max:500',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_geofence_meter' => 'nullable|integer|min:10|max:50000',
            'geofence_radius_meter' => 'nullable|integer|min:10|max:50000',
            'jam_masuk' => 'nullable',
            'work_start_hour' => 'nullable',
            'jam_pulang' => 'nullable',
            'work_end_hour' => 'nullable',
            'toleransi_keterlambatan_menit' => 'nullable|integer|min:0|max:240',
            'grace_period_minutes' => 'nullable|integer|min:0|max:240',
            'status_geofence_aktif' => 'nullable',
            'is_geofence_active' => 'nullable',
        ]);

        $config = OfficeConfig::first();
        if (! $config) {
            $config = new OfficeConfig();
        }

        $data = [];
        if (array_key_exists('nama_kantor', $validated) || array_key_exists('name', $validated)) {
            $val = $validated['nama_kantor'] ?? $validated['name'];
            if ($val !== null) {
                $data['nama_kantor'] = (string) $val;
            }
        }
        if (array_key_exists('alamat_kantor', $validated) || array_key_exists('address', $validated)) {
            $val = $validated['alamat_kantor'] ?? $validated['address'];
            if ($val !== null) {
                $data['alamat_kantor'] = (string) $val;
            }
        }
        if (array_key_exists('latitude', $validated) && $validated['latitude'] !== null) {
            $data['latitude'] = (float) $validated['latitude'];
        }
        if (array_key_exists('longitude', $validated) && $validated['longitude'] !== null) {
            $data['longitude'] = (float) $validated['longitude'];
        }
        if (array_key_exists('radius_geofence_meter', $validated) || array_key_exists('geofence_radius_meter', $validated)) {
            $val = $validated['radius_geofence_meter'] ?? $validated['geofence_radius_meter'];
            if ($val !== null) {
                $data['radius_geofence_meter'] = (int) $val;
            }
        }
        if (array_key_exists('jam_masuk', $validated) || array_key_exists('work_start_hour', $validated)) {
            $val = $validated['jam_masuk'] ?? $validated['work_start_hour'];
            if ($val !== null && trim((string) $val) !== '') {
                $parts = explode(':', trim((string) $val));
                $data['jam_masuk'] = count($parts) >= 2 ? sprintf('%02d:%02d', (int) $parts[0], (int) $parts[1]) : trim((string) $val);
            }
        }
        if (array_key_exists('jam_pulang', $validated) || array_key_exists('work_end_hour', $validated)) {
            $val = $validated['jam_pulang'] ?? $validated['work_end_hour'];
            if ($val !== null && trim((string) $val) !== '') {
                $parts = explode(':', trim((string) $val));
                $data['jam_pulang'] = count($parts) >= 2 ? sprintf('%02d:%02d', (int) $parts[0], (int) $parts[1]) : trim((string) $val);
            }
        }
        if (array_key_exists('toleransi_keterlambatan_menit', $validated) || array_key_exists('grace_period_minutes', $validated)) {
            $val = $validated['toleransi_keterlambatan_menit'] ?? $validated['grace_period_minutes'];
            if ($val !== null) {
                $data['toleransi_keterlambatan_menit'] = (int) $val;
            }
        }
        if (array_key_exists('status_geofence_aktif', $validated) || array_key_exists('is_geofence_active', $validated)) {
            $val = $validated['status_geofence_aktif'] ?? $validated['is_geofence_active'];
            $data['status_geofence_aktif'] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }

        $config->fill($data);
        $config->save();

        return response()->json([
            'message' => 'Konfigurasi kantor berhasil diperbarui.',
            'config' => $config->fresh(),
        ]);
    }
}
