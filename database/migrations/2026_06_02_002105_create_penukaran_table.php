<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penukaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('produk_id')
                  ->nullable()                        // null jika tipe cash
                  ->constrained('produk')
                  ->onDelete('set null');
            $table->enum('tipe', ['produk', 'cash'])->default('produk');
            $table->integer('jumlah')->default(1);    // qty produk atau nominal cash
            $table->integer('total_poin');             // total poin yang dipotong
            $table->enum('status', ['pending', 'selesai', 'dibatalkan'])
                  ->default('pending');
            $table->string('metode_cash')->nullable(); // Transfer BCA, GoPay, dst
            $table->string('no_rekening')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penukaran');
    }
};