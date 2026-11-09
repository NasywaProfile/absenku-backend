<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('nama', 255);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('peran', 30)->default('EMPLOYEE'); // SUPER_ADMIN, ADMIN, EMPLOYEE
            $table->string('status', 30)->default('PENDING_APPROVAL'); // ACTIVE, PENDING_APPROVAL, REJECTED
            $table->string('tipe_kepegawaian', 50)->default('CONTRACT'); // PERMANENT, CONTRACT, INTERN, FREELANCE
            $table->string('divisi', 100)->nullable();
            $table->string('jabatan', 100)->nullable();
            $table->text('foto_profil')->nullable();
            $table->string('nomor_telepon', 50)->nullable();
            $table->string('tanggal_bergabung', 30)->nullable();
            $table->integer('sisa_cuti')->default(12);
            $table->string('nama_bank', 100)->nullable();
            $table->string('nomor_rekening', 100)->nullable();
            $table->string('nama_pemilik_rekening', 150)->nullable();
            $table->string('nama_kontak_darurat', 150)->nullable();
            $table->string('nomor_kontak_darurat', 50)->nullable();
            $table->text('alamat')->nullable();
            $table->string('waktu_daftar', 50)->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->boolean('profil_lengkap')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
};
