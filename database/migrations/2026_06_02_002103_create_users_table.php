<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['nasabah', 'admin'])->default('nasabah');
            $table->boolean('is_verified')->default(false);
            $table->string('no_hp')->nullable();
            $table->string('id_nasabah')->nullable()->unique(); // BS-20231012
            $table->integer('total_poin')->default(0);
            $table->string('fcm_token')->nullable(); // Firebase push notif
            $table->string('foto_profil')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};