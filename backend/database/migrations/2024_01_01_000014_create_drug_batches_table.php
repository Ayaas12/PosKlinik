<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('batch_number', 100);           // nomor batch / lot
            $table->integer('quantity_received');           // jumlah yang diterima
            $table->integer('quantity_remaining');          // sisa stok dari batch ini
            $table->decimal('harga_beli', 12, 2)->default(0); // harga beli per satuan batch ini
            $table->date('tanggal_kadaluarsa')->nullable(); // expiry date per batch
            $table->date('tanggal_diterima');               // tanggal penerimaan
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['drug_id', 'batch_number']);
        });

        // Add batch_id reference to stock_movements so movements can be traced to a lot
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->bigInteger('batch_id')->nullable()->unsigned()->after('reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
        Schema::dropIfExists('drug_batches');
    }
};
