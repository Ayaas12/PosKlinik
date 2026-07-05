<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->string('label');          // display name: "Strip", "Box (10 Strip)", "Botol 60ml"
            $table->string('satuan');         // unit code: strip, kapsul, botol, box, pcs, etc.
            $table->integer('konversi')->default(1); // how many base units this equals (e.g. 1 box = 10 strip)
            $table->decimal('harga_jual', 12, 2); // selling price for this unit
            $table->boolean('is_default')->default(false); // default unit shown in POS
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_units');
    }
};
