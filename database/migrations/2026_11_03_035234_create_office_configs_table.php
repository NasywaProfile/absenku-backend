<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konfigurasi_kantor', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kantor', 255);
            $table->text('alamat_kantor');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('radius_geofence_meter')->default(100);
            $table->string('jam_masuk', 10)->default('08:00');
            $table->string('jam_pulang', 10)->default('17:00');
            $table->integer('toleransi_keterlambatan_menit')->default(0);
            $table->boolean('status_geofence_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konfigurasi_kantor');
    }
};
