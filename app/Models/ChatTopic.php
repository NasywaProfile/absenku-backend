<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatTopic extends Model
{
    use HasFactory;

    protected $table = 'topik_diskusi';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'judul_topik',
        'status_topik',
        'id_anggota_ditandai',
        'berkas',
        'tautan',
        'gambar',
        'catatan_singkat',
        'catatan_lengkap',
        'dibuat_oleh_id',
        'dibuat_oleh_nama',
        'dibuat_oleh_foto',
    ];

    protected function casts(): array
    {
        return [
            'id_anggota_ditandai' => 'array',
            'berkas' => 'array',
            'tautan' => 'array',
            'gambar' => 'array',
        ];
    }

    protected $appends = [
        'title',
        'status',
        'tagged_teammate_ids',
        'taggedTeammateIds',
        'files',
        'links',
        'images',
        'short_notes',
        'shortNotes',
        'long_notes',
        'longNotes',
        'created_by',
        'createdBy',
        'created_by_name',
        'createdByName',
        'created_by_avatar',
        'createdByAvatar',
        'created_at_formatted',
        'createdAt',
        'updatedAt',
        'comments',
    ];

    public function getTitleAttribute() { return $this->judul_topik; }
    public function getStatusAttribute() { return $this->status_topik; }
    public function getTaggedTeammateIdsAttribute()
    {
        $val = $this->id_anggota_ditandai;
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) return $decoded;
            return array_values(array_filter(array_map('trim', explode(',', $val))));
        }
        return is_array($val) ? array_values($val) : [];
    }
    public function getFilesAttribute() { return $this->berkas ?? []; }
    public function getLinksAttribute() { return $this->tautan ?? []; }
    public function getImagesAttribute() { return $this->gambar ?? []; }
    public function getShortNotesAttribute() { return $this->catatan_singkat ?? ''; }
    public function getLongNotesAttribute() { return $this->catatan_lengkap ?? ''; }
    public function getCreatedByAttribute() { return $this->dibuat_oleh_id; }
    public function getCreatedByNameAttribute() { return $this->dibuat_oleh_nama; }
    public function getCreatedByAvatarAttribute() { return $this->dibuat_oleh_foto; }
    public function getCreatedAtAttribute() { return $this->attributes['created_at'] ?? ''; }
    public function getUpdatedAtAttribute() { return $this->attributes['updated_at'] ?? ''; }

    public function getCommentsAttribute()
    {
        return $this->messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'topicId' => $msg->topik_id,
                'userId' => $msg->pengguna_id,
                'userName' => $msg->nama_pengirim,
                'userAvatar' => $msg->foto_pengirim,
                'userRole' => $msg->peran_pengirim,
                'notes' => $msg->isi_pesan,
                'timestamp' => $msg->waktu_kirim,
                'attachments' => $msg->lampiran ?? [],
                'type' => 'COMMENT',
            ];
        });
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'topik_id');
    }
}
