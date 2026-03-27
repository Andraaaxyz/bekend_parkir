<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_transaksi', function (Blueprint $table) {
            $table->id('id_parkir');
            $table->foreignId('id_kendaraan')->constrained('tb_kendaraan', 'id_kendaraan')->onDelete('cascade');
            $table->dateTime('waktu_masuk');
            $table->dateTime('waktu_keluar')->nullable();
            $table->foreignId('id_tarif')->constrained('tb_tarif', 'id_tarif')->onDelete('restrict');
            $table->decimal('durasi', 8, 2)->nullable()->comment('in hours');
            $table->decimal('biaya_total', 12, 2)->nullable();
            $table->enum('status', ['masuk', 'keluar'])->default('masuk');
            $table->foreignId('id_user')->constrained('tb_user', 'id_user')->onDelete('restrict');
            $table->foreignId('id_area')->constrained('tb_area_parkir', 'id_area')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_transaksi');
    }
};
