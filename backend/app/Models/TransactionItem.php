<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id', 'drug_id', 'drug_name', 'harga_jual', 'quantity', 'diskon', 'subtotal',
    ];

    protected $casts = [
        'harga_jual' => 'decimal:2',
        'diskon' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
