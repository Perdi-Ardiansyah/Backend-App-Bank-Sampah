<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            $table->string('aksi');          // 'modified pricing', 'verified user', dst
            $table->string('model')->nullable();   // 'KategoriSampah', 'User', dst
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('data_lama')->nullable();
            $table->json('data_baru')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index('admin_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};