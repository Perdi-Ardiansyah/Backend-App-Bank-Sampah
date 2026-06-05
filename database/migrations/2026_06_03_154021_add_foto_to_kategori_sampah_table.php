<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kategori_sampah', function (Blueprint $table) {
            // Menambahkan kolom foto setelah kolom icon_name dan sifatnya opsional (nullable)
            $table->string('foto')->nullable()->after('icon_name');
        });
    }

    public function down(): void
    {
        Schema::table('kategori_sampah', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};