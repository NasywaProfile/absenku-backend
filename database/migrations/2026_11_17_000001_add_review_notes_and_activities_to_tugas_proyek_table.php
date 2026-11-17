<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tugas_proyek', function (Blueprint $table) {
            if (! Schema::hasColumn('tugas_proyek', 'catatan_revisi')) {
                $table->text('catatan_revisi')->nullable()->after('lampiran');
            }
            if (! Schema::hasColumn('tugas_proyek', 'catatan_penyerahan')) {
                $table->text('catatan_penyerahan')->nullable()->after('catatan_revisi');
            }
            if (! Schema::hasColumn('tugas_proyek', 'aktivitas')) {
                $table->json('aktivitas')->nullable()->after('catatan_penyerahan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tugas_proyek', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('tugas_proyek', 'catatan_revisi')) $cols[] = 'catatan_revisi';
            if (Schema::hasColumn('tugas_proyek', 'catatan_penyerahan')) $cols[] = 'catatan_penyerahan';
            if (Schema::hasColumn('tugas_proyek', 'aktivitas')) $cols[] = 'aktivitas';
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
