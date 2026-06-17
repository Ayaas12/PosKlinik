<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_transaksi')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // kasir
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('diskon', 14, 2)->default(0); // discount amount
            $table->decimal('pajak', 14, 2)->default(0);  // tax
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('bayar', 14, 2)->default(0);  // amount paid
            $table->decimal('kembalian', 14, 2)->default(0); // change
            $table->string('metode_bayar', 20)->default('tunai'); // tunai, qris, transfer, kartu
            $table->string('status', 20)->default('selesai');     // pending, selesai, dibatalkan, diretur
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
