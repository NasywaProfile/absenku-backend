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
            'jam_masuk' => 'nullable|date_format:H:i',
            'work_start_hour' => 'nullable|date_format:H:i',
            'jam_pulang' => 'nullable|date_format:H:i',
            'work_end_hour' => 'nullable|date_format:H:i',
            'toleransi_keterlambatan_menit' => 'nullable|integer|min:0|max:240',
            'grace_period_minutes' => 'nullable|integer|min:0|max:240',
            'status_geofence_aktif' => 'nullable|boolean',
            'is_geofence_active' => 'nullable|boolean',
        ]);

        $config = OfficeConfig::first();
        if (! $config) {
            $config = new OfficeConfig();
        }

        $data = [];
        if (array_key_exists('nama_kantor', $validated) || array_key_exists('name', $validated)) {
            $data['nama_kantor'] = $validated['nama_kantor'] ?? $validated['name'];
        }
        if (array_key_exists('alamat_kantor', $validated) || array_key_exists('address', $validated)) {
            $data['alamat_kantor'] = $validated['alamat_kantor'] ?? $validated['address'];
        }
        if (array_key_exists('latitude', $validated) && $validated['latitude'] !== null) {
            $data['latitude'] = $validated['latitude'];
        }
        if (array_key_exists('longitude', $validated) && $validated['longitude'] !== null) {
            $data['longitude'] = $validated['longitude'];
        }
        if (array_key_exists('radius_geofence_meter', $validated) || array_key_exists('geofence_radius_meter', $validated)) {
            $data['radius_geofence_meter'] = $validated['radius_geofence_meter'] ?? $validated['geofence_radius_meter'];
        }
        if (array_key_exists('jam_masuk', $validated) || array_key_exists('work_start_hour', $validated)) {
            $data['jam_masuk'] = $validated['jam_masuk'] ?? $validated['work_start_hour'];
        }
        if (array_key_exists('jam_pulang', $validated) || array_key_exists('work_end_hour', $validated)) {
            $data['jam_pulang'] = $validated['jam_pulang'] ?? $validated['work_end_hour'];
        }
        if (array_key_exists('toleransi_keterlambatan_menit', $validated) || array_key_exists('grace_period_minutes', $validated)) {
            $data['toleransi_keterlambatan_menit'] = $validated['toleransi_keterlambatan_menit'] ?? $validated['grace_period_minutes'];
        }
        if (array_key_exists('status_geofence_aktif', $validated) || array_key_exists('is_geofence_active', $validated)) {
            $data['status_geofence_aktif'] = (bool) ($validated['status_geofence_aktif'] ?? $validated['is_geofence_active']);
        }

        $config->fill($data);
        $config->save();

        return response()->json([
            'message' => 'Konfigurasi kantor berhasil diperbarui.',
            'config' => $config->fresh(),
        ]);
    }
}
