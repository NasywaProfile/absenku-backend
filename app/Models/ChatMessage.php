<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'pesan_diskusi';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'topik_id',
        'pengguna_id',
        'nama_pengirim',
        'foto_pengirim',
        'peran_pengirim',
        'tipe_kepegawaian',
        'isi_pesan',
        'waktu_kirim',
        'kanal',
        'lampiran',
    ];

    protected function casts(): array
    {
        return [
            'lampiran' => 'array',
        ];
    }

    protected $appends = [
        'user_id',
        'user_name',
        'user_avatar',
        'user_role',
        'employment_type',
        'content',
        'timestamp',
        'channel_id',
        'attachments',
    ];

    public function getUserIdAttribute() { return $this->pengguna_id; }
    public function getUserNameAttribute() { return $this->nama_pengirim; }
    public function getUserAvatarAttribute() { return $this->foto_pengirim; }
    public function getUserRoleAttribute() { return $this->peran_pengirim; }
    public function getEmploymentTypeAttribute() { return $this->tipe_kepegawaian; }
    public function getContentAttribute() { return $this->isi_pesan; }
    public function getTimestampAttribute() { return $this->waktu_kirim; }
    public function getChannelIdAttribute() { return $this->kanal; }
    public function getAttachmentsAttribute() { return $this->lampiran; }

    public function topic()
    {
        return $this->belongsTo(ChatTopic::class, 'topik_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }
}
