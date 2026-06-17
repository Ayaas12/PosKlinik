<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'drug_id', 'user_id', 'type', 'quantity',
        'stok_before', 'stok_after', 'reference_type', 'reference_id',
        'batch_id', 'catatan',
    ];

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
