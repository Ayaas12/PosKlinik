<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $thisMonth = now()->format('Y-m');

        // Revenue today
        $revenueToday = Transaction::where('status', 'selesai')
            ->whereDate('created_at', $today)
            ->sum('total');

        // Revenue this month
        $revenueMonth = Transaction::where('status', 'selesai')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('total');

        // Transaction count today
        $txToday = Transaction::where('status', 'selesai')
            ->whereDate('created_at', $today)
            ->count();

        // Low stock drugs
        $lowStockCount = Drug::whereColumn('stok', '<=', 'stok_minimum')
            ->where('is_active', true)
            ->count();

        // Near expiry (within 30 days)
        $nearExpiryCount = Drug::where('is_active', true)
            ->whereNotNull('tanggal_kadaluarsa')
            ->where('tanggal_kadaluarsa', '<=', now()->addDays(30))
            ->count();

        // Revenue last 7 days for chart
        $last7Days = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            $revenue = Transaction::where('status', 'selesai')
                ->whereDate('created_at', $date)
                ->sum('total');
            return [
                'date'    => $date,
                'label'   => now()->subDays($daysAgo)->format('D, d M'),
                'revenue' => (float) $revenue,
            ];
        });

        // Top 5 selling drugs this month
        $topDrugs = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'selesai')
            ->whereYear('transactions.created_at', now()->year)
            ->whereMonth('transactions.created_at', now()->month)
            ->select('transaction_items.drug_name', DB::raw('SUM(transaction_items.quantity) as total_qty'), DB::raw('SUM(transaction_items.subtotal) as total_revenue'))
            ->groupBy('transaction_items.drug_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // Low stock list
        $lowStockDrugs = Drug::with('category')
            ->whereColumn('stok', '<=', 'stok_minimum')
            ->where('is_active', true)
            ->orderBy('stok')
            ->limit(10)
            ->get(['id', 'kode_obat', 'name', 'stok', 'stok_minimum', 'category_id']);

        return response()->json([
            'stats' => [
                'revenue_today'        => (float) $revenueToday,
                'revenue_month'        => (float) $revenueMonth,
                'transactions_today'   => $txToday,
                'low_stock_count'      => $lowStockCount,
                'near_expiry_count'    => $nearExpiryCount,

            ],
            'revenue_chart'   => $last7Days,
            'top_drugs'       => $topDrugs,
            'low_stock_drugs' => $lowStockDrugs,
        ]);
    }
}
