<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            // nullable so existing rows are not broken
            $table->foreignId('drug_unit_id')
                ->nullable()
                ->after('drug_id')
                ->constrained('drug_units')
                ->nullOnDelete();

            // snapshot of the unit label at time of sale
            $table->string('satuan')->nullable()->after('drug_unit_id');

            // how many base stock units were consumed per sold unit (default 1)
            $table->integer('konversi')->default(1)->after('satuan');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropForeign(['drug_unit_id']);
            $table->dropColumn(['drug_unit_id', 'satuan', 'konversi']);
        });
    }
};
