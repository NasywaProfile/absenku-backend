<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KanbanTask extends Model
{
    use HasFactory;

    protected $table = 'tugas_proyek';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'judul_tugas',
        'deskripsi',
        'status_tugas',
        'prioritas',
        'ditugaskan_ke',
        'nama_petugas',
        'foto_petugas',
        'daftar_anggota',
        'dibuat_oleh',
        'tenggat_waktu',
        'lampiran',
        'catatan_revisi',
        'catatan_penyerahan',
        'aktivitas',
    ];

    protected function casts(): array
    {
        return [
            'daftar_anggota' => 'array',
            'lampiran' => 'array',
            'aktivitas' => 'array',
        ];
    }

    protected $appends = [
        'title',
        'description',
        'status',
        'priority',
        'assigned_to',
        'assigned_name',
        'assigned_avatar',
        'assignees',
        'created_by',
        'due_date',
        'attachments',
        'review_notes',
        'submission_notes',
        'activities',
    ];

    public function getTitleAttribute() { return $this->judul_tugas; }
    public function getDescriptionAttribute() { return $this->deskripsi; }
    public function getStatusAttribute() { return $this->status_tugas; }
    public function getPriorityAttribute() { return $this->prioritas; }
    public function getAssignedToAttribute() { return $this->ditugaskan_ke; }
    public function getAssignedNameAttribute() { return $this->nama_petugas; }
    public function getAssignedAvatarAttribute() { return $this->foto_petugas; }
    public function getAssigneesAttribute() { return $this->daftar_anggota; }
    public function getCreatedByAttribute() { return $this->dibuat_oleh; }
    public function getDueDateAttribute() { return $this->tenggat_waktu; }
    public function getAttachmentsAttribute() { return $this->lampiran ?? []; }
    public function getReviewNotesAttribute() { return $this->catatan_revisi; }
    public function getSubmissionNotesAttribute() { return $this->catatan_penyerahan; }
    public function getActivitiesAttribute() { return $this->aktivitas ?? []; }
}
