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

        if (! in_array($user->peran, ['SUPER_ADMIN', 'ADMIN'])) {
            return response()->json(['message' => 'Hanya Admin yang dapat mengubah konfigurasi kantor.'], 403);
        }

        $config = OfficeConfig::first();
        if (! $config) {
            $config = new OfficeConfig();
        }

        $data = [];
        if ($request->has('nama_kantor') || $request->has('name')) {
            $data['nama_kantor'] = $request->input('nama_kantor', $request->input('name'));
        }
        if ($request->has('alamat_kantor') || $request->has('address')) {
            $data['alamat_kantor'] = $request->input('alamat_kantor', $request->input('address'));
        }
        if ($request->has('latitude')) {
            $data['latitude'] = $request->input('latitude');
        }
        if ($request->has('longitude')) {
            $data['longitude'] = $request->input('longitude');
        }
        if ($request->has('radius_geofence_meter') || $request->has('geofence_radius_meter')) {
            $data['radius_geofence_meter'] = $request->input('radius_geofence_meter', $request->input('geofence_radius_meter'));
        }
        if ($request->has('jam_masuk') || $request->has('work_start_hour')) {
            $data['jam_masuk'] = $request->input('jam_masuk', $request->input('work_start_hour'));
        }
        if ($request->has('jam_pulang') || $request->has('work_end_hour')) {
            $data['jam_pulang'] = $request->input('jam_pulang', $request->input('work_end_hour'));
        }
        if ($request->has('toleransi_keterlambatan_menit') || $request->has('grace_period_minutes')) {
            $data['toleransi_keterlambatan_menit'] = $request->input('toleransi_keterlambatan_menit', $request->input('grace_period_minutes'));
        }
        if ($request->has('status_geofence_aktif') || $request->has('is_geofence_active')) {
            $data['status_geofence_aktif'] = $request->input('status_geofence_aktif', $request->input('is_geofence_active'));
        }

        $config->fill($data);
        $config->save();

        return response()->json([
            'message' => 'Konfigurasi kantor berhasil diperbarui.',
            'config' => $config->fresh(),
        ]);
    }
}
