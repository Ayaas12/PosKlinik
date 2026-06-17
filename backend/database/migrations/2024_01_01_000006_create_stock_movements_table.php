<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('masuk'); // masuk, keluar, penyesuaian, retur
            $table->integer('quantity'); // positive = in, negative = out
            $table->integer('stok_before');
            $table->integer('stok_after');
            $table->string('reference_type')->nullable(); // transaction, prescription, etc.
            $table->bigInteger('reference_id')->nullable()->unsigned();
            $table->text('catatan')->nullable(); // notes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
