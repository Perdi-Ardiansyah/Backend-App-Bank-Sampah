<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setoran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('kategori_id')
                  ->constrained('kategori_sampah')
                  ->onDelete('restrict');
            $table->foreignId('admin_id')             // admin yang input
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            $table->decimal('berat_kg', 8, 2);
            $table->integer('poin_didapat');
            $table->enum('status', ['pending', 'selesai', 'dibatalkan'])
                  ->default('pending');
            $table->string('lokasi_tps')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setoran');
    }
};