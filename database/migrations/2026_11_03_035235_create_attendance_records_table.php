<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('pengguna_id', 50)->index();
            $table->string('nama_karyawan', 255);
            $table->text('foto_karyawan')->nullable();
            $table->string('divisi', 100)->nullable();
            $table->string('tipe_kepegawaian', 50)->nullable();
            $table->date('tanggal')->index();
            $table->string('jam_masuk', 20);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('jarak_meter')->default(0);
            $table->string('status_lokasi', 50)->default('IN_OFFICE');
            $table->string('status_kehadiran', 50)->default('ON_TIME');
            $table->integer('menit_keterlambatan')->default(0);
            $table->string('mode_kerja', 30)->default('WFO');
            $table->text('catatan')->nullable();
            $table->string('status_sinkronisasi', 30)->default('SYNCED');
            $table->timestamps();

            $table->foreign('pengguna_id')->references('id')->on('pengguna')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};
