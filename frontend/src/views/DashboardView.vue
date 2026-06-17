<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/utils/api'
import { formatRupiah, formatDate } from '@/utils/format'
import StatCard from '@/components/StatCard.vue'

const stats = ref(null)
const chart = ref([])
const topDrugs = ref([])
const lowStock = ref([])
const loading = ref(true)

const maxRevenue = computed(() =>
  chart.value.length ? Math.max(...chart.value.map(d => d.revenue), 1) : 1
)

onMounted(async () => {
  try {
    const res = await api.get('/dashboard')
    stats.value = res.data.stats
    chart.value = res.data.revenue_chart
    topDrugs.value = res.data.top_drugs
    lowStock.value = res.data.low_stock_drugs
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Page header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Dashboard</h2>
        <p class="text-sm text-gray-500 mt-0.5">Selamat datang! Berikut ringkasan hari ini.</p>
      </div>
      <p class="text-sm text-gray-400">{{ new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="card p-5 animate-pulse">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-gray-200 rounded-xl"></div>
          <div class="space-y-2 flex-1">
            <div class="h-3 bg-gray-200 rounded w-3/4"></div>
            <div class="h-6 bg-gray-200 rounded w-1/2"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stat Cards -->
    <div v-else class="grid grid-cols-2 lg:grid-cols-3 gap-4">
      <StatCard
        title="Pendapatan Hari Ini"
        :value="formatRupiah(stats?.revenue_today)"
        icon="💰"
        color="teal"
        sub="Transaksi selesai"
      />
      <StatCard
        title="Pendapatan Bulan Ini"
        :value="formatRupiah(stats?.revenue_month)"
        icon="📅"
        color="blue"
        sub="Bulan berjalan"
      />
      <StatCard
        title="Transaksi Hari Ini"
        :value="stats?.transactions_today"
        icon="🛒"
        color="green"
        sub="Transaksi selesai"
      />
      <StatCard
        title="Stok Menipis"
        :value="stats?.low_stock_count"
        icon="⚠️"
        color="orange"
        sub="Perlu restok segera"
        :alert="stats?.low_stock_count > 0"
      />
      <StatCard
        title="Hampir Kadaluarsa"
        :value="stats?.near_expiry_count"
        icon="📆"
        color="red"
        sub="Dalam 30 hari"
        :alert="stats?.near_expiry_count > 0"
      />
    </div>

    <!-- Charts & Tables row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Revenue Bar Chart (last 7 days) -->
      <div class="card p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Pendapatan 7 Hari Terakhir</h3>
        <div v-if="loading" class="h-40 bg-gray-100 animate-pulse rounded-lg"></div>
        <div v-else class="flex items-end gap-2 h-40">
          <div
            v-for="day in chart"
            :key="day.date"
            class="flex-1 flex flex-col items-center gap-1"
          >
            <span class="text-xs text-gray-500 font-medium">
              {{ formatRupiah(day.revenue).replace('Rp\xa0', '') }}
            </span>
            <div
              class="w-full bg-primary-500 rounded-t-md transition-all duration-500 hover:bg-primary-600 min-h-[4px]"
              :style="{ height: `${Math.max((day.revenue / maxRevenue) * 100, 2)}%` }"
              :title="formatRupiah(day.revenue)"
            ></div>
            <span class="text-xs text-gray-400 text-center leading-none">
              {{ day.label.split(',')[0] }}
            </span>
          </div>
        </div>
      </div>

      <!-- Top Selling Drugs -->
      <div class="card p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Obat Terlaris Bulan Ini</h3>
        <div v-if="loading" class="space-y-3">
          <div v-for="i in 5" :key="i" class="h-8 bg-gray-100 animate-pulse rounded"></div>
        </div>
        <div v-else-if="topDrugs.length === 0" class="text-center py-8 text-gray-400 text-sm">
          Belum ada data transaksi bulan ini.
        </div>
        <div v-else class="space-y-3">
          <div v-for="(drug, idx) in topDrugs" :key="drug.drug_name" class="flex items-center gap-3">
            <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-700 text-xs font-bold flex items-center justify-center flex-shrink-0">
              {{ idx + 1 }}
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ drug.drug_name }}</p>
              <div class="flex items-center gap-2 mt-0.5">
                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-primary-500 rounded-full"
                    :style="{ width: `${(drug.total_qty / topDrugs[0].total_qty) * 100}%` }"
                  ></div>
                </div>
                <span class="text-xs text-gray-500 whitespace-nowrap">{{ drug.total_qty }} pcs</span>
              </div>
            </div>
            <span class="text-xs font-semibold text-primary-700 whitespace-nowrap">
              {{ formatRupiah(drug.total_revenue) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Low Stock Alert Table -->
    <div v-if="!loading && lowStock.length > 0" class="card">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-amber-500 text-lg">⚠️</span>
          <h3 class="text-sm font-semibold text-gray-700">Peringatan Stok Menipis</h3>
        </div>
        <router-link to="/stok" class="text-xs text-primary-600 hover:text-primary-700 font-medium">
          Lihat Semua →
        </router-link>
      </div>
      <div class="table-wrapper rounded-none rounded-b-xl border-0">
        <table class="table">
          <thead>
            <tr>
              <th>Kode Obat</th>
              <th>Nama Obat</th>
              <th>Stok Saat Ini</th>
              <th>Stok Minimum</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="drug in lowStock" :key="drug.id">
              <td><span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ drug.kode_obat }}</span></td>
              <td class="font-medium">{{ drug.name }}</td>
              <td>
                <span class="font-bold" :class="drug.stok === 0 ? 'text-red-600' : 'text-amber-600'">
                  {{ drug.stok }}
                </span>
              </td>
              <td class="text-gray-500">{{ drug.stok_minimum }}</td>
              <td>
                <span v-if="drug.stok === 0" class="badge-red">Habis</span>
                <span v-else class="badge-yellow">Menipis</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
