<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficeConfig extends Model
{
    use HasFactory;

    protected $table = 'konfigurasi_kantor';

    protected $fillable = [
        'nama_kantor',
        'alamat_kantor',
        'latitude',
        'longitude',
        'radius_geofence_meter',
        'jam_masuk',
        'jam_pulang',
        'toleransi_keterlambatan_menit',
        'status_geofence_aktif',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_geofence_meter' => 'integer',
            'toleransi_keterlambatan_menit' => 'integer',
            'status_geofence_aktif' => 'boolean',
        ];
    }

    protected $appends = [
        'name',
        'address',
        'geofence_radius_meter',
        'work_start_hour',
        'work_end_hour',
        'grace_period_minutes',
        'is_geofence_active',
    ];

    public function getNameAttribute() { return $this->nama_kantor; }
    public function getAddressAttribute() { return $this->alamat_kantor; }
    public function getGeofenceRadiusMeterAttribute() { return $this->radius_geofence_meter; }
    public function getWorkStartHourAttribute() { return $this->jam_masuk; }
    public function getWorkEndHourAttribute() { return $this->jam_pulang; }
    public function getGracePeriodMinutesAttribute() { return $this->toleransi_keterlambatan_menit; }
    public function getIsGeofenceActiveAttribute() { return (bool) $this->status_geofence_aktif; }
}
