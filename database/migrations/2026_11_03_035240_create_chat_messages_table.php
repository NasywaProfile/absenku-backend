<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesan_diskusi', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('topik_id', 50)->nullable()->index();
            $table->string('pengguna_id', 50)->index();
            $table->string('nama_pengirim', 255);
            $table->text('foto_pengirim')->nullable();
            $table->string('peran_pengirim', 30)->default('EMPLOYEE');
            $table->string('tipe_kepegawaian', 50)->default('CONTRACT');
            $table->text('isi_pesan');
            $table->string('waktu_kirim', 30);
            $table->string('kanal', 50)->default('general');
            $table->json('lampiran')->nullable();
            $table->timestamps();

            $table->foreign('pengguna_id')->references('id')->on('pengguna')->onDelete('cascade');
            $table->foreign('topik_id')->references('id')->on('topik_diskusi')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesan_diskusi');
    }
};
