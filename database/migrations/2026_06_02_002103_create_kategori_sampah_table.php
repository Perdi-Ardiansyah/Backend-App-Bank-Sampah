<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_sampah', function (Blueprint $table) {
            $table->id();
            $table->string('nama');                    // Plastik, Logam, dst
            $table->text('deskripsi')->nullable();
            $table->integer('poin_per_kg');            // poin per kilogram
            $table->string('icon_name')->nullable();   // recycling, hardware, dst
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_sampah');
    }
};