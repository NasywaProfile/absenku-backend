<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetSuperAdminCommand extends Command
{
    protected $signature = 'absenku:set-superadmin
        {--email= : Alamat email baru untuk akun Super Admin}
        {--password= : Password baru yang kuat (minimal 12 karakter)}
        {--name= : Nama tampilan Super Admin}
        {--revoke-other-admins : Turunkan/hapus akun admin/dummy default lama jika ada}';

    protected $description = 'Mengganti kredensial akun Super Admin produksi dengan aman tanpa migrate:fresh.';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email Super Admin baru');
        $email = strtolower(trim((string) $email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat email tidak valid.');
            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: $this->secret('Password Super Admin baru (min. 12 karakter)'));
        if (mb_strlen($password) < 12) {
            $this->error('Password minimal harus terdiri dari 12 karakter.');
            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: 'Super Admin'));

        $conflict = User::whereRaw('LOWER(email) = ?', [$email])
            ->where('id', '!=', 'usr_super_admin')
            ->first();

        if ($conflict) {
            $this->error("Email {$email} sudah dipakai oleh user lain ({$conflict->id}).");
            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $password, $name): void {
            $existing = User::find('usr_super_admin');

            if ($existing) {
                $existing->update([
                    'nama' => $name ?: $existing->nama,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'peran' => 'SUPER_ADMIN',
                    'status' => 'ACTIVE',
                    'profil_lengkap' => true,
                ]);
                $targetUser = $existing;
            } else {
                $targetUser = User::create([
                    'id' => 'usr_super_admin',
                    'nama' => $name ?: 'Super Admin',
                    'email' => $email,
                    'password' => Hash::make($password),
                    'peran' => 'SUPER_ADMIN',
                    'status' => 'ACTIVE',
                    'tipe_kepegawaian' => 'PERMANENT',
                    'divisi' => 'Direksi Eksekutif',
                    'jabatan' => 'Super Administrator',
                    'sisa_cuti' => 24,
                    'tanggal_bergabung' => date('Y-m-d'),
                    'waktu_daftar' => date('Y-m-d H:i'),
                    'profil_lengkap' => true,
                ]);
            }

            // Cabut semua token lama agar sesi sebelumnya langsung kedaluwarsa
            $targetUser->tokens()->delete();

            if ($this->option('revoke-other-admins')) {
                $legacyEmails = [
                    'superadmin@gmail.com',
                    'budi.admin@gmail.com',
                    'alissa.karyawan@gmail.com',
                    'budi.santoso@absenku.id',
                    'siti.rahmawati@absenku.id',
                    'ahmad.fauzi@absenku.id',
                    'dewi.lestari@absenku.id',
                    'rizky.pratama@absenku.id',
                    'nadia.putri@absenku.id',
                    'dimas.wahyudi@absenku.id',
                ];

                $legacyIds = User::whereIn(DB::raw('LOWER(email)'), $legacyEmails)
                    ->where('id', '!=', 'usr_super_admin')
                    ->pluck('id');

                if ($legacyIds->isNotEmpty()) {
                    DB::table('personal_access_tokens')
                        ->whereIn('tokenable_id', $legacyIds)
                        ->delete();
                    User::whereIn('id', $legacyIds)->delete();
                }
            }
        });

        $this->info("Kredensial Super Admin ({$email}) berhasil diperbarui & semua token lama telah dicabut.");

        return self::SUCCESS;
    }
}
