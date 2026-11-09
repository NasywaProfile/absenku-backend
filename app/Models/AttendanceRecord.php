<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $table = 'presensi';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'pengguna_id',
        'nama_karyawan',
        'foto_karyawan',
        'divisi',
        'tipe_kepegawaian',
        'tanggal',
        'jam_masuk',
        'latitude',
        'longitude',
        'jarak_meter',
        'status_lokasi',
        'status_kehadiran',
        'menit_keterlambatan',
        'mode_kerja',
        'catatan',
        'status_sinkronisasi',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'jarak_meter' => 'integer',
            'menit_keterlambatan' => 'integer',
        ];
    }

    protected $appends = [
        'user_id',
        'user_name',
        'user_avatar',
        'department',
        'employment_type',
        'date',
        'clock_in_time',
        'clock_in_lat',
        'clock_in_lng',
        'clock_in_distance_meter',
        'clock_in_location_status',
        'clock_in_status',
        'clock_in_late_minutes',
        'work_mode',
        'notes',
        'sync_status',
    ];

    public function getUserIdAttribute() { return $this->pengguna_id; }
    public function getUserNameAttribute() { return $this->nama_karyawan; }
    public function getUserAvatarAttribute() { return $this->foto_karyawan; }
    public function getDepartmentAttribute() { return $this->divisi; }
    public function getEmploymentTypeAttribute() { return $this->tipe_kepegawaian; }
    public function getDateAttribute() { return $this->tanggal; }
    public function getClockInTimeAttribute() { return $this->jam_masuk; }
    public function getClockInLatAttribute() { return $this->latitude; }
    public function getClockInLngAttribute() { return $this->longitude; }
    public function getClockInDistanceMeterAttribute() { return $this->jarak_meter; }
    public function getClockInLocationStatusAttribute() { return $this->status_lokasi; }
    public function getClockInStatusAttribute() { return $this->status_kehadiran; }
    public function getClockInLateMinutesAttribute() { return $this->menit_keterlambatan; }
    public function getWorkModeAttribute() { return $this->mode_kerja; }
    public function getNotesAttribute() { return $this->catatan; }
    public function getSyncStatusAttribute() { return $this->status_sinkronisasi; }

    public function user()
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }
}
