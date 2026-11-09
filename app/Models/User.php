<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Nama tabel di database MySQL (Bahasa Indonesia untuk TA)
     */
    protected $table = 'pengguna';

    /**
     * Konfigurasi primary key non-incrementing string (e.g. usr_super_admin)
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Atribut yang dapat diisi secara massal.
     */
    protected $fillable = [
        'id',
        'nama',
        'email',
        'password',
        'peran',
        'status',
        'tipe_kepegawaian',
        'divisi',
        'jabatan',
        'foto_profil',
        'nomor_telepon',
        'jenis_kelamin',
        'tanggal_lahir',
        'tanggal_bergabung',
        'sisa_cuti',
        'nama_bank',
        'nomor_rekening',
        'nama_pemilik_rekening',
        'nama_kontak_darurat',
        'nomor_kontak_darurat',
        'alamat',
        'waktu_daftar',
        'alasan_penolakan',
        'profil_lengkap',
    ];

    /**
     * Atribut yang disembunyikan saat serialisasi JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting tipe data atribut.
     */
    protected function casts(): array
    {
        return [
            'profil_lengkap' => 'boolean',
            'sisa_cuti' => 'integer',
        ];
    }

    /**
     * Accessor tambahan agar serasi dengan antarmuka frontend (alias dwibahasa)
     */
    protected $appends = [
        'name',
        'role',
        'department',
        'position',
        'avatar',
        'phone',
        'gender',
        'birth_date',
        'birthDate',
        'employment_type',
        'employmentType',
        'join_date',
        'leave_balance',
        'is_profile_complete',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'emergency_contact_name',
        'emergency_contact_phone',
        'address',
        'registered_at',
    ];

    public function getNameAttribute() { return $this->nama; }
    public function getRoleAttribute() { return $this->peran; }
    public function getDepartmentAttribute() { return $this->divisi; }
    public function getPositionAttribute() { return $this->jabatan; }
    public function getAvatarAttribute() { return $this->foto_profil; }
    public function getPhoneAttribute() { return $this->nomor_telepon; }
    public function getGenderAttribute() { return $this->jenis_kelamin ?: 'L'; }
    public function getBirthDateAttribute() { return $this->tanggal_lahir ?: '2000-01-01'; }
    public function getEmploymentTypeAttribute() { return $this->tipe_kepegawaian; }
    public function getJoinDateAttribute() { return $this->tanggal_bergabung; }
    public function getLeaveBalanceAttribute() { return $this->sisa_cuti; }
    public function getIsProfileCompleteAttribute() { return (bool) $this->profil_lengkap; }
    public function getBankNameAttribute() { return $this->nama_bank; }
    public function getBankAccountNumberAttribute() { return $this->nomor_rekening; }
    public function getBankAccountHolderAttribute() { return $this->nama_pemilik_rekening; }
    public function getEmergencyContactNameAttribute() { return $this->nama_kontak_darurat; }
    public function getEmergencyContactPhoneAttribute() { return $this->nomor_kontak_darurat; }
    public function getAddressAttribute() { return $this->alamat; }
    public function getRegisteredAtAttribute() { return $this->waktu_daftar; }
}
