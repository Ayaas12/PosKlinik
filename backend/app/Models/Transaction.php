<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'nomor_transaksi', 'user_id',
        'subtotal', 'diskon', 'pajak', 'total', 'bayar', 'kembalian',
        'metode_bayar', 'status', 'catatan',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'diskon' => 'decimal:2',
        'pajak' => 'decimal:2',
        'total' => 'decimal:2',
        'bayar' => 'decimal:2',
        'kembalian' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function returns()
    {
        return $this->hasMany(ReturnModel::class, 'transaction_id');
    }
}
