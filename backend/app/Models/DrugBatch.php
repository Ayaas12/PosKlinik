<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugBatch extends Model
{
    protected $fillable = [
        'drug_id', 'supplier_id', 'received_by',
        'batch_number', 'quantity_received', 'quantity_remaining',
        'harga_beli', 'tanggal_kadaluarsa', 'tanggal_diterima', 'catatan',
    ];

    protected $casts = [
        'harga_beli'          => 'decimal:2',
        'tanggal_kadaluarsa'  => 'date',
        'tanggal_diterima'    => 'date',
    ];

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isExpired(): bool
    {
        return $this->tanggal_kadaluarsa !== null &&
            $this->tanggal_kadaluarsa->isPast();
    }

    public function isNearExpiry(int $days = 90): bool
    {
        return $this->tanggal_kadaluarsa !== null &&
            !$this->isExpired() &&
            $this->tanggal_kadaluarsa->lte(now()->addDays($days));
    }
}
