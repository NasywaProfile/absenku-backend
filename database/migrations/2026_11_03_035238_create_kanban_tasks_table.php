<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas_proyek', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('judul_tugas', 255);
            $table->text('deskripsi')->nullable();
            $table->string('status_tugas', 50)->default('TODO'); // TODO, IN_PROGRESS, DONE, REVISION
            $table->string('prioritas', 30)->default('MEDIUM'); // LOW, MEDIUM, HIGH, URGENT
            $table->string('ditugaskan_ke', 50)->nullable()->index();
            $table->string('nama_petugas', 255)->nullable();
            $table->text('foto_petugas')->nullable();
            $table->json('daftar_anggota')->nullable();
            $table->string('dibuat_oleh', 100)->nullable();
            $table->date('tenggat_waktu')->nullable();
            $table->json('lampiran')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_proyek');
    }
};
