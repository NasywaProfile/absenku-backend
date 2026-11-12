<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('topik_diskusi', 'dihapus_oleh_ids')) {
            Schema::table('topik_diskusi', function (Blueprint $table) {
                $table->json('dihapus_oleh_ids')->nullable()->after('id_anggota_ditandai');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('topik_diskusi', 'dihapus_oleh_ids')) {
            Schema::table('topik_diskusi', function (Blueprint $table) {
                $table->dropColumn('dihapus_oleh_ids');
            });
        }
    }
};
