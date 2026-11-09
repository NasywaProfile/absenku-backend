<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topik_diskusi', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('judul_topik', 255);
            $table->string('status_topik', 50)->default('NEED_COMMENTS'); // NEED_COMMENTS, IN_PROGRESS, CLOSED
            $table->json('id_anggota_ditandai')->nullable();
            $table->json('berkas')->nullable();
            $table->json('tautan')->nullable();
            $table->json('gambar')->nullable();
            $table->text('catatan_singkat')->nullable();
            $table->longText('catatan_lengkap')->nullable();
            $table->string('dibuat_oleh_id', 50);
            $table->string('dibuat_oleh_nama', 255);
            $table->text('dibuat_oleh_foto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topik_diskusi');
    }
};
