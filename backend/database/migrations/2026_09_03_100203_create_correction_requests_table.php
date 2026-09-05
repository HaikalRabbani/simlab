<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('examination_id');
            $table->foreign('examination_id')->references('id')->on('examinations')->onDelete('cascade');
            $table->unsignedBigInteger('diajukan_oleh');
            $table->foreign('diajukan_oleh')->references('id')->on('users')->onDelete('cascade');
            $table->text('alasan');
            $table->enum('status', ['menunggu','disetujui','ditolak']);
            $table->unsignedBigInteger('ditinjau_oleh')->nullable();
            $table->foreign('ditinjau_oleh')->references('id')->on('users')->onDelete('set null');
            $table->text('catatan_peninjau')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
    }
};
