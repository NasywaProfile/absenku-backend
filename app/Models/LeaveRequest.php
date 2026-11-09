<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_izin';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'pengguna_id',
        'nama_karyawan',
        'foto_karyawan',
        'divisi',
        'kategori_izin',
        'tanggal_mulai',
        'tanggal_selesai',
        'jumlah_hari',
        'alasan',
        'nama_lampiran',
        'data_lampiran',
        'tipe_lampiran',
        'status_pengajuan',
        'waktu_pengajuan',
        'disetujui_oleh',
        'waktu_persetujuan',
        'alasan_penolakan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_hari' => 'integer',
        ];
    }

    protected $appends = [
        'user_id',
        'user_name',
        'user_avatar',
        'department',
        'category',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'attachment_name',
        'attachment_data',
        'attachment_type',
        'status',
        'applied_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    public function getUserIdAttribute() { return $this->pengguna_id; }
    public function getUserNameAttribute() { return $this->nama_karyawan; }
    public function getUserAvatarAttribute() { return $this->foto_karyawan; }
    public function getDepartmentAttribute() { return $this->divisi; }
    public function getCategoryAttribute() { return $this->kategori_izin; }
    public function getStartDateAttribute() { return $this->tanggal_mulai; }
    public function getEndDateAttribute() { return $this->tanggal_selesai; }
    public function getTotalDaysAttribute() { return $this->jumlah_hari; }
    public function getReasonAttribute() { return $this->alasan; }
    public function getAttachmentNameAttribute() { return $this->nama_lampiran; }
    public function getAttachmentDataAttribute() { return $this->data_lampiran; }
    public function getAttachmentTypeAttribute() { return $this->tipe_lampiran; }
    public function getStatusAttribute() { return $this->status_pengajuan; }
    public function getAppliedAtAttribute() { return $this->waktu_pengajuan; }
    public function getReviewedByAttribute() { return $this->disetujui_oleh; }
    public function getReviewedAtAttribute() { return $this->waktu_persetujuan; }
    public function getRejectionReasonAttribute() { return $this->alasan_penolakan; }

    public function user()
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }
}
