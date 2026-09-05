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
        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique(); // idempotency key dari device (offline sync)
            $table->unsignedBigInteger('session_id')->nullable();
            $table->unsignedBigInteger('schedule_id')->nullable()->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->unsignedBigInteger('petugas_id')->index();
            $table->foreign('petugas_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamp('waktu_input')->index(); // waktu dari device petugas
            $table->string('pendamping');
            $table->text('catatan_petugas')->nullable();
            $table->enum('rencana_tindak_lanjut', ['rujuk_uji_konfirmasi', 'pantau_rutin', 'skrining_ulang'])->nullable();
            $table->boolean('sampel_disegel')->nullable();
            $table->string('kode_segel')->nullable();
            $table->enum('status_kirim', ['pending_sync', 'terkirim'])->default('terkirim');
            $table->boolean('is_locked')->default(false);
            $table->string('strip_lot_code');
            $table->date('strip_expiry_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examinations');
    }
};
