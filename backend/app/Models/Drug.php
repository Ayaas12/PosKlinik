<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Drug extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kode_obat', 'name', 'generic_name', 'category_id', 'supplier_id',
        'barcode', 'satuan', 'harga_beli', 'harga_jual', 'stok', 'stok_minimum',
        'tanggal_kadaluarsa', 'lokasi_rak', 'description', 'memerlukan_resep', 'is_active',
    ];

    protected $appends = ['has_units'];

    protected $casts = [
        'harga_beli' => 'decimal:2',
        'harga_jual' => 'decimal:2',
        'memerlukan_resep' => 'boolean',
        'is_active' => 'boolean',
        'tanggal_kadaluarsa' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function batches()
    {
        return $this->hasMany(DrugBatch::class);
    }

    public function units()
    {
        return $this->hasMany(DrugUnit::class)->orderBy('konversi');
    }

    public function getHasUnitsAttribute(): bool
    {
        return $this->relationLoaded('units') ? $this->units->isNotEmpty() : $this->units()->exists();
    }

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->stok <= $this->stok_minimum;
    }

    public function isNearExpiry(int $days = 30): bool
    {
        return $this->tanggal_kadaluarsa !== null &&
            $this->tanggal_kadaluarsa->lte(now()->addDays($days));
    }
}
