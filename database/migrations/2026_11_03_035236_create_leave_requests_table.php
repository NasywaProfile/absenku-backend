<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_izin', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('pengguna_id', 50)->index();
            $table->string('nama_karyawan', 255);
            $table->text('foto_karyawan')->nullable();
            $table->string('divisi', 100)->nullable();
            $table->string('kategori_izin', 50); // LEAVE, WFH, SICK, BUSINESS_TRIP
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->integer('jumlah_hari')->default(1);
            $table->text('alasan');
            $table->string('nama_lampiran', 255)->nullable();
            $table->longText('data_lampiran')->nullable();
            $table->string('tipe_lampiran', 50)->nullable();
            $table->string('status_pengajuan', 50)->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->string('waktu_pengajuan', 50)->nullable();
            $table->string('disetujui_oleh', 150)->nullable();
            $table->string('waktu_persetujuan', 50)->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->timestamps();

            $table->foreign('pengguna_id')->references('id')->on('pengguna')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izin');
    }
};
