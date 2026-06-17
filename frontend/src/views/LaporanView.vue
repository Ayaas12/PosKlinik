<script setup>
import { ref, onMounted } from 'vue'
import api from '@/utils/api'
import { formatRupiah, formatDate } from '@/utils/format'
import {
  exportSalesPDF,    exportSalesXLSX,
  exportStockPDF,    exportStockXLSX,
  exportProfitLossPDF, exportProfitLossXLSX,
  exportBestSellerPDF, exportBestSellerXLSX,
} from '@/utils/export'

// ─── State ────────────────────────────────────────────────────────────────────
const activeTab = ref('sales')

const dateFrom = ref(new Date(new Date().setDate(1)).toISOString().slice(0, 10))
const dateTo   = ref(new Date().toISOString().slice(0, 10))

// Sales
const salesSummary  = ref({})
const salesChart    = ref([])
const topItems      = ref([])
const transactions  = ref([])
const salesLoading  = ref(false)
const txLoading     = ref(false)

// Stock
const stockSummary = ref({})
const stockDrugs   = ref([])
const stockLoading = ref(false)

// P/L
const plSummary = ref({})
const plItems   = ref([])
const plLoading = ref(false)

// Export loading flags
const exporting = ref({ pdf: false, xlsx: false })

// ─── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchSales() {
  salesLoading.value = true
  try {
    const res = await api.get('/reports/sales', {
      params: { date_from: dateFrom.value, date_to: dateTo.value },
    })
    salesSummary.value = res.data.summary    ?? {}
    salesChart.value   = res.data.chart      ?? []
    topItems.value     = res.data.top_items  ?? []
  } catch (e) { console.error(e) }
  finally { salesLoading.value = false }
}

async function fetchTransactions() {
  txLoading.value = true
  try {
    const res = await api.get('/transactions', {
      params: { date_from: dateFrom.value, date_to: dateTo.value, per_page: 50, status: 'selesai' },
    })
    transactions.value = res.data.data ?? []
  } catch (_) {}
  finally { txLoading.value = false }
}

async function fetchStock() {
  stockLoading.value = true
  try {
    const res = await api.get('/reports/stock')
    stockSummary.value = res.data.summary ?? {}
    stockDrugs.value   = res.data.drugs   ?? []
  } catch (e) { console.error(e) }
  finally { stockLoading.value = false }
}

async function fetchPL() {
  plLoading.value = true
  try {
    const res = await api.get('/reports/profit-loss', {
      params: { date_from: dateFrom.value, date_to: dateTo.value },
    })
    plSummary.value = res.data.summary ?? {}
    plItems.value   = res.data.items   ?? []
  } catch (e) { console.error(e) }
  finally { plLoading.value = false }
}

function applyFilter() {
  fetchSales()
  fetchTransactions()
  fetchPL()
}

function loadTab(tab) {
  activeTab.value = tab
  if (tab === 'sales'    && !salesSummary.value.total_transaksi) { fetchSales(); fetchTransactions() }
  if (tab === 'stock'    && !stockDrugs.value.length)             fetchStock()
  if (tab === 'pl'       && !plItems.value.length)                fetchPL()
  if (tab === 'terlaris' && !topItems.value.length)               fetchSales()
}

onMounted(() => { fetchSales(); fetchTransactions(); fetchStock(); fetchPL() })

// ─── Export handlers ──────────────────────────────────────────────────────────
function exportPayload() {
  return {
    summary:      salesSummary.value,
    chart:        salesChart.value,
    topItems:     topItems.value,
    transactions: transactions.value,
    dateFrom:     dateFrom.value,
    dateTo:       dateTo.value,
  }
}

async function doExport(type, fn) {
  exporting.value[type] = true
  try { await fn() } finally { exporting.value[type] = false }
}

function exportCurrentPDF() {
  if (activeTab.value === 'sales')    return doExport('pdf', () => exportSalesPDF(exportPayload()))
  if (activeTab.value === 'stock')    return doExport('pdf', () => exportStockPDF({ summary: stockSummary.value, drugs: stockDrugs.value }))
  if (activeTab.value === 'pl')       return doExport('pdf', () => exportProfitLossPDF({ summary: plSummary.value, items: plItems.value, dateFrom: dateFrom.value, dateTo: dateTo.value }))
  if (activeTab.value === 'terlaris') return doExport('pdf', () => exportBestSellerPDF({ topItems: topItems.value, dateFrom: dateFrom.value, dateTo: dateTo.value }))
}

function exportCurrentXLSX() {
  if (activeTab.value === 'sales')    return doExport('xlsx', () => exportSalesXLSX(exportPayload()))
  if (activeTab.value === 'stock')    return doExport('xlsx', () => exportStockXLSX({ summary: stockSummary.value, drugs: stockDrugs.value }))
  if (activeTab.value === 'pl')       return doExport('xlsx', () => exportProfitLossXLSX({ summary: plSummary.value, items: plItems.value, dateFrom: dateFrom.value, dateTo: dateTo.value }))
  if (activeTab.value === 'terlaris') return doExport('xlsx', () => exportBestSellerXLSX({ topItems: topItems.value, dateFrom: dateFrom.value, dateTo: dateTo.value }))
}

// ─── Tabs config ──────────────────────────────────────────────────────────────
const tabs = [
  { key: 'sales',    label: '📊 Penjualan' },
  { key: 'terlaris', label: '🏆 Produk Terlaris' },
  { key: 'stock',    label: '📦 Stok' },
  { key: 'pl',       label: '💹 Laba Rugi' },
]
</script>

<template>
  <div class="space-y-4">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Laporan &amp; Analitik</h2>
        <p class="text-sm text-gray-500">Ringkasan data penjualan, stok, dan keuangan</p>
      </div>

      <!-- Export buttons -->
      <div class="flex gap-2">
        <button
          @click="exportCurrentPDF"
          :disabled="exporting.pdf"
          class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-60"
        >
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
          </svg>
          {{ exporting.pdf ? 'Mengekspor...' : 'Export PDF' }}
        </button>
        <button
          @click="exportCurrentXLSX"
          :disabled="exporting.xlsx"
          class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-60"
        >
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
          </svg>
          {{ exporting.xlsx ? 'Mengekspor...' : 'Export Excel' }}
        </button>
      </div>
    </div>

    <!-- Date filter (hidden for stock tab) -->
    <div v-if="activeTab !== 'stock'" class="card p-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="form-label">Dari Tanggal</label>
        <input v-model="dateFrom" type="date" class="form-input" />
      </div>
      <div>
        <label class="form-label">Sampai Tanggal</label>
        <input v-model="dateTo" type="date" class="form-input" />
      </div>
      <button @click="applyFilter" class="btn-primary">🔄 Terapkan Filter</button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 bg-gray-100 p-1 rounded-xl w-fit">
      <button
        v-for="tab in tabs" :key="tab.key"
        @click="loadTab(tab.key)"
        class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-150"
        :class="activeTab === tab.key
          ? 'bg-white shadow text-primary-700'
          : 'text-gray-600 hover:text-gray-800'"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- ── SALES TAB ──────────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'sales'" class="space-y-4">

      <!-- Summary cards -->
      <div v-if="salesLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="card p-5 animate-pulse h-24"></div>
      </div>
      <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-5 bg-primary-50 border-primary-200">
          <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total Pendapatan</p>
          <p class="text-xl font-bold text-primary-700">{{ formatRupiah(salesSummary.total_pendapatan ?? 0) }}</p>
        </div>
        <div class="card p-5 bg-green-50 border-green-200">
          <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total Transaksi</p>
          <p class="text-xl font-bold text-green-700">{{ salesSummary.total_transaksi ?? 0 }}</p>
        </div>
        <div class="card p-5 bg-blue-50 border-blue-200">
          <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Rata-rata/Transaksi</p>
          <p class="text-xl font-bold text-blue-700">{{ formatRupiah(salesSummary.rata_rata ?? 0) }}</p>
        </div>
        <div class="card p-5 bg-amber-50 border-amber-200">
          <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total Diskon</p>
          <p class="text-xl font-bold text-amber-700">{{ formatRupiah(salesSummary.total_diskon ?? 0) }}</p>
        </div>
      </div>

      <!-- Transaction table -->
      <div class="card">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="text-sm font-semibold text-gray-700">Daftar Transaksi</h3>
        </div>
        <div class="table-wrapper rounded-none rounded-b-xl border-0">
          <table class="table">
            <thead>
              <tr>
                <th>No. Transaksi</th>
                <th>Tanggal</th>
                <th>Kasir</th>
                <th>Total</th>
                <th>Metode</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="txLoading" v-for="i in 5" :key="i">
                <td colspan="6"><div class="h-4 bg-gray-100 animate-pulse rounded"></div></td>
              </tr>
              <tr v-else-if="transactions.length === 0">
                <td colspan="6" class="text-center py-8 text-gray-400 text-sm">
                  Tidak ada transaksi pada periode ini.
                </td>
              </tr>
              <tr v-else v-for="tx in transactions" :key="tx.id">
                <td><span class="font-mono text-xs font-medium">{{ tx.nomor_transaksi }}</span></td>
                <td>{{ formatDate(tx.created_at) }}</td>
                <td>{{ tx.user?.name || '—' }}</td>
                <td class="font-bold text-primary-700">{{ formatRupiah(tx.total) }}</td>
                <td><span class="badge-blue capitalize">{{ tx.metode_bayar }}</span></td>
                <td>
                  <span :class="tx.status === 'selesai' ? 'badge-green' : tx.status === 'dibatalkan' ? 'badge-red' : 'badge-yellow'">
                    {{ tx.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── BEST SELLERS TAB ───────────────────────────────────────────────── -->
    <div v-if="activeTab === 'terlaris'" class="space-y-4">
      <div v-if="salesLoading" class="card p-8 animate-pulse h-48"></div>
      <div v-else-if="topItems.length === 0" class="card p-16 text-center text-gray-400">
        <div class="text-4xl mb-2">🏆</div>
        <p class="text-sm">Belum ada data penjualan untuk periode ini.</p>
      </div>
      <div v-else class="card">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="text-sm font-semibold text-gray-700">Top {{ topItems.length }} Produk Terlaris</h3>
        </div>
        <div class="table-wrapper rounded-none rounded-b-xl border-0">
          <table class="table">
            <thead>
              <tr>
                <th class="w-12">#</th>
                <th>Nama Obat</th>
                <th class="text-right">Qty Terjual</th>
                <th class="text-right">Total Pendapatan</th>
                <th>Proporsi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in topItems" :key="item.drug_name">
                <td>
                  <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                    :class="idx === 0 ? 'bg-yellow-200 text-yellow-800'
                           : idx === 1 ? 'bg-gray-200 text-gray-700'
                           : idx === 2 ? 'bg-orange-200 text-orange-800'
                           : 'bg-gray-100 text-gray-600'">
                    {{ idx + 1 }}
                  </span>
                </td>
                <td class="font-medium">{{ item.drug_name }}</td>
                <td class="text-right font-semibold">{{ item.total_qty }}</td>
                <td class="text-right font-semibold text-primary-700">{{ formatRupiah(item.total_revenue) }}</td>
                <td class="w-36">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                      <div class="h-full bg-primary-500 rounded-full"
                        :style="{ width: topItems[0].total_qty > 0 ? (item.total_qty / topItems[0].total_qty * 100) + '%' : '0%' }">
                      </div>
                    </div>
                    <span class="text-xs text-gray-500 w-10 text-right">
                      {{ topItems[0].total_qty > 0 ? Math.round(item.total_qty / topItems[0].total_qty * 100) : 0 }}%
                    </span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── STOCK TAB ──────────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'stock'" class="space-y-4">

      <!-- Summary -->
      <div v-if="stockLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="card p-5 animate-pulse h-20"></div>
      </div>
      <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-4">
          <p class="text-xs text-gray-500 mb-1">Total Obat</p>
          <p class="text-2xl font-bold text-gray-800">{{ stockSummary.total_obat ?? 0 }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-gray-500 mb-1">Nilai Stok</p>
          <p class="text-xl font-bold text-primary-700">{{ formatRupiah(stockSummary.total_nilai_stok ?? 0) }}</p>
        </div>
        <div class="card p-4 bg-amber-50">
          <p class="text-xs text-gray-500 mb-1">Stok Menipis</p>
          <p class="text-2xl font-bold text-amber-600">{{ stockSummary.low_stock ?? 0 }}</p>
        </div>
        <div class="card p-4 bg-red-50">
          <p class="text-xs text-gray-500 mb-1">Hampir Kadaluarsa</p>
          <p class="text-2xl font-bold text-red-600">{{ stockSummary.near_expiry ?? 0 }}</p>
        </div>
      </div>

      <!-- Table -->
      <div class="card">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="text-sm font-semibold text-gray-700">Detail Stok Obat</h3>
        </div>
        <div class="table-wrapper rounded-none rounded-b-xl border-0">
          <table class="table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Obat</th>
                <th>Kategori</th>
                <th>Stok</th>
                <th>Min. Stok</th>
                <th>Nilai Stok</th>
                <th>Kadaluarsa</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="stockLoading" v-for="i in 8" :key="i">
                <td colspan="8"><div class="h-4 bg-gray-100 animate-pulse rounded"></div></td>
              </tr>
              <tr v-else-if="stockDrugs.length === 0">
                <td colspan="8" class="text-center py-8 text-gray-400">Tidak ada data stok.</td>
              </tr>
              <tr v-else v-for="d in stockDrugs" :key="d.id">
                <td><span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ d.kode_obat }}</span></td>
                <td class="font-medium">{{ d.name }}</td>
                <td><span class="badge-blue">{{ d.category?.name }}</span></td>
                <td :class="d.stok === 0 ? 'text-red-600 font-bold' : d.stok <= d.stok_minimum ? 'text-amber-600 font-bold' : 'text-green-600 font-bold'">
                  {{ d.stok }}
                </td>
                <td class="text-gray-500">{{ d.stok_minimum }}</td>
                <td class="font-semibold">{{ formatRupiah(d.harga_beli * d.stok) }}</td>
                <td class="text-xs">{{ formatDate(d.tanggal_kadaluarsa) }}</td>
                <td>
                  <span v-if="d.stok === 0" class="badge-red">Habis</span>
                  <span v-else-if="d.stok <= d.stok_minimum" class="badge-yellow">Menipis</span>
                  <span v-else class="badge-green">Normal</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── PROFIT/LOSS TAB ────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'pl'" class="space-y-4">

      <!-- Summary cards -->
      <div v-if="plLoading" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="card p-5 animate-pulse h-24"></div>
      </div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="card p-5 bg-green-50 border-green-200">
          <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total Pendapatan</p>
          <p class="text-xl font-bold text-green-700">{{ formatRupiah(plSummary.total_pendapatan ?? 0) }}</p>
        </div>
        <div class="card p-5 bg-red-50 border-red-200">
          <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total HPP</p>
          <p class="text-xl font-bold text-red-700">{{ formatRupiah(plSummary.total_hpp ?? 0) }}</p>
        </div>
        <div class="card p-5"
          :class="(plSummary.laba_kotor ?? 0) >= 0 ? 'bg-primary-50 border-primary-200' : 'bg-red-50 border-red-200'">
          <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Laba Kotor</p>
          <p class="text-xl font-bold" :class="(plSummary.laba_kotor ?? 0) >= 0 ? 'text-primary-700' : 'text-red-700'">
            {{ formatRupiah(plSummary.laba_kotor ?? 0) }}
          </p>
        </div>
        <div class="card p-5 bg-blue-50 border-blue-200">
          <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Margin</p>
          <p class="text-xl font-bold text-blue-700">{{ plSummary.margin ?? 0 }}%</p>
        </div>
      </div>

      <!-- P/L detail table -->
      <div class="card">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="text-sm font-semibold text-gray-700">Detail Laba per Produk</h3>
        </div>
        <div class="table-wrapper rounded-none rounded-b-xl border-0">
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>Nama Obat</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Pendapatan</th>
                <th class="text-right">HPP</th>
                <th class="text-right">Laba Kotor</th>
                <th class="text-right">Margin</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="plLoading" v-for="i in 6" :key="i">
                <td colspan="7"><div class="h-4 bg-gray-100 animate-pulse rounded"></div></td>
              </tr>
              <tr v-else-if="plItems.length === 0">
                <td colspan="7" class="text-center py-8 text-gray-400">Tidak ada data untuk periode ini.</td>
              </tr>
              <tr v-else v-for="(item, idx) in plItems" :key="item.drug_name">
                <td class="text-gray-400 text-xs">{{ idx + 1 }}</td>
                <td class="font-medium">{{ item.drug_name }}</td>
                <td class="text-right">{{ item.total_qty }}</td>
                <td class="text-right font-semibold text-green-700">{{ formatRupiah(item.total_revenue) }}</td>
                <td class="text-right text-red-600">{{ formatRupiah(item.total_cost) }}</td>
                <td class="text-right font-bold"
                  :class="item.gross_profit >= 0 ? 'text-primary-700' : 'text-red-700'">
                  {{ formatRupiah(item.gross_profit) }}
                </td>
                <td class="text-right text-xs text-gray-500">
                  {{ item.total_revenue > 0 ? ((item.gross_profit / item.total_revenue) * 100).toFixed(1) : '0.0' }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</template>
