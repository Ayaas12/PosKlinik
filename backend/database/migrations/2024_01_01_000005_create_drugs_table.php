<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('kode_obat')->unique();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('barcode')->nullable()->unique();
            $table->string('satuan')->default('pcs'); // unit: pcs, strip, botol, etc.
            $table->decimal('harga_beli', 12, 2)->default(0); // purchase price
            $table->decimal('harga_jual', 12, 2)->default(0); // selling price
            $table->integer('stok')->default(0);
            $table->integer('stok_minimum')->default(10); // low stock threshold
            $table->date('tanggal_kadaluarsa')->nullable();
            $table->string('lokasi_rak')->nullable(); // shelf location
            $table->text('description')->nullable();
            $table->boolean('memerlukan_resep')->default(false); // prescription required
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
