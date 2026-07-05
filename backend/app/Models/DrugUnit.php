<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugUnit extends Model
{
    protected $fillable = [
        'drug_id', 'label', 'satuan', 'konversi', 'harga_jual', 'is_default',
    ];

    protected $casts = [
        'harga_jual' => 'decimal:2',
        'konversi'   => 'integer',
        'is_default' => 'boolean',
    ];

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
