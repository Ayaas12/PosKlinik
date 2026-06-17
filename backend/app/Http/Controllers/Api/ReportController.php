<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Sales report – filterable by date range.
     */
    public function sales(Request $request)
    {
        $request->validate([
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by'   => ['nullable', 'string', 'in:day,month'],
        ]);

        $from    = $request->date_from ?? now()->startOfMonth()->toDateString();
        $to      = $request->date_to   ?? now()->toDateString();
        $groupBy = $request->group_by  ?? 'day';

        $transactions = Transaction::where('status', 'selesai')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->get();

        $groupedRevenue = DB::table('transactions')
            ->where('status', 'selesai')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->select(
                DB::raw($groupBy === 'month'
                    ? "TO_CHAR(created_at, 'YYYY-MM') as period"
                    : "DATE(created_at) as period"),
                DB::raw('COUNT(*) as total_transaksi'),
                DB::raw('SUM(total) as total_pendapatan'),
                DB::raw('SUM(diskon) as total_diskon')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Top selling items
        $topItems = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'selesai')
            ->whereBetween(DB::raw('DATE(transactions.created_at)'), [$from, $to])
            ->select(
                'transaction_items.drug_name',
                DB::raw('SUM(transaction_items.quantity) as total_qty'),
                DB::raw('SUM(transaction_items.subtotal) as total_revenue')
            )
            ->groupBy('transaction_items.drug_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => [
                'total_transaksi'  => $transactions->count(),
                'total_pendapatan' => (float) $transactions->sum('total'),
                'total_diskon'     => (float) $transactions->sum('diskon'),
                'rata_rata'        => $transactions->count() > 0 ? (float) $transactions->avg('total') : 0,
            ],
            'chart'     => $groupedRevenue,
            'top_items' => $topItems,
            'date_from' => $from,
            'date_to'   => $to,
        ]);
    }

    /**
     * Stock report.
     */
    public function stock(Request $request)
    {
        $drugs = Drug::with(['category:id,name', 'supplier:id,name'])
            ->where('is_active', true)
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->low_stock, fn($q) => $q->whereColumn('stok', '<=', 'stok_minimum'))
            ->when($request->near_expiry, fn($q) =>
                $q->whereNotNull('tanggal_kadaluarsa')
                  ->where('tanggal_kadaluarsa', '<=', now()->addDays(30))
            )
            ->orderBy('name')
            ->get();

        $totalNilaiStok = $drugs->sum(fn($d) => $d->stok * $d->harga_beli);

        return response()->json([
            'summary' => [
                'total_obat'       => $drugs->count(),
                'total_nilai_stok' => (float) $totalNilaiStok,
                'low_stock'        => $drugs->filter(fn($d) => $d->stok <= $d->stok_minimum)->count(),
                'near_expiry'      => $drugs->filter(fn($d) => $d->tanggal_kadaluarsa && $d->tanggal_kadaluarsa->lte(now()->addDays(30)))->count(),
            ],
            'drugs' => $drugs,
        ]);
    }

    /**
     * Profit/Loss report.
     */
    public function profitLoss(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = $request->date_from ?? now()->startOfMonth()->toDateString();
        $to   = $request->date_to   ?? now()->toDateString();

        $items = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('drugs', 'transaction_items.drug_id', '=', 'drugs.id')
            ->where('transactions.status', 'selesai')
            ->whereBetween(DB::raw('DATE(transactions.created_at)'), [$from, $to])
            ->select(
                'transaction_items.drug_name',
                DB::raw('SUM(transaction_items.quantity) as total_qty'),
                DB::raw('SUM(transaction_items.subtotal) as total_revenue'),
                DB::raw('SUM(transaction_items.quantity * drugs.harga_beli) as total_cost'),
                DB::raw('SUM(transaction_items.subtotal - transaction_items.quantity * drugs.harga_beli) as gross_profit')
            )
            ->groupBy('transaction_items.drug_name')
            ->orderByDesc('gross_profit')
            ->get();

        $totalRevenue = $items->sum('total_revenue');
        $totalCost    = $items->sum('total_cost');
        $grossProfit  = $totalRevenue - $totalCost;

        return response()->json([
            'summary' => [
                'total_pendapatan' => (float) $totalRevenue,
                'total_hpp'        => (float) $totalCost,
                'laba_kotor'       => (float) $grossProfit,
                'margin'           => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
            ],
            'items'     => $items,
            'date_from' => $from,
            'date_to'   => $to,
        ]);
    }

    /**
     * Stock movement history.
     */
    public function stockMovements(Request $request)
    {
        $movements = \App\Models\StockMovement::with(['drug:id,name,kode_obat', 'user:id,name'])
            ->when($request->drug_id, fn($q) => $q->where('drug_id', $request->drug_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($movements);
    }
}
