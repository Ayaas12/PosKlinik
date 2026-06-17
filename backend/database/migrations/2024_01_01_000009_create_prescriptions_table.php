<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_resep')->unique();
            $table->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // kasir who processed
            $table->date('tanggal_resep');
            $table->string('status', 20)->default('pending'); // pending, diproses, selesai, dibatalkan
            $table->text('catatan')->nullable();
            $table->bigInteger('transaction_id')->nullable()->unsigned(); // linked transaction after processing
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
