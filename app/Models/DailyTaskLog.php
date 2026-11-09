<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyTaskLog extends Model
{
    use HasFactory;

    protected $table = 'logbook_harian';

    protected $fillable = [
        'pengguna_id',
        'nama_karyawan',
        'tanggal',
        'daftar_tugas',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'daftar_tugas' => 'array',
        ];
    }

    protected $appends = [
        'user_id',
        'user_name',
        'date',
        'tasks',
        'notes',
    ];

    public function getUserIdAttribute() { return $this->pengguna_id; }
    public function getUserNameAttribute() { return $this->nama_karyawan; }
    public function getDateAttribute() { return $this->tanggal; }
    public function getTasksAttribute() { return $this->daftar_tugas; }
    public function getNotesAttribute() { return $this->catatan; }

    public function user()
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }
}
