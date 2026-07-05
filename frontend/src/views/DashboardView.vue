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
  <div class="space-y-8">

    <!-- Page header -->
    <div class="flex items-end justify-between border-b border-gray-100 pb-5">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 tracking-tight">Dashboard</h2>
        <p class="text-sm text-gray-400 mt-0.5">Ringkasan aktivitas hari ini</p>
      </div>
      <p class="text-xs text-gray-400 font-mono tabular-nums">
        {{ new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
      </p>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-2 lg:grid-cols-3 gap-3">
      <div v-for="i in 5" :key="i" class="bg-white border border-gray-100 rounded-xl p-5 animate-pulse">
        <div class="h-3 bg-gray-100 rounded w-1/2 mb-3"></div>
        <div class="h-7 bg-gray-100 rounded w-3/4"></div>
      </div>
    </div>

    <!-- Stat Cards -->
    <div v-else class="grid grid-cols-2 lg:grid-cols-3 gap-3">
      <StatCard
        title="Pendapatan Hari Ini"
        :value="formatRupiah(stats?.revenue_today)"
        color="teal"
        sub="Transaksi selesai"
      />
      <StatCard
        title="Pendapatan Bulan Ini"
        :value="formatRupiah(stats?.revenue_month)"
        color="blue"
        sub="Bulan berjalan"
      />
      <StatCard
        title="Transaksi Hari Ini"
        :value="stats?.transactions_today"
        color="green"
        sub="Transaksi selesai"
      />
      <StatCard
        title="Stok Menipis"
        :value="stats?.low_stock_count"
        color="orange"
        sub="Perlu restok segera"
        :alert="stats?.low_stock_count > 0"
      />
      <StatCard
        title="Hampir Kadaluarsa"
        :value="stats?.near_expiry_count"
        color="red"
        sub="Dalam 30 hari"
        :alert="stats?.near_expiry_count > 0"
      />
    </div>

    <!-- Charts & Tables row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      <!-- Revenue Bar Chart (last 7 days) -->
      <div class="bg-white border border-gray-100 rounded-xl p-5">
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-5">Pendapatan 7 Hari Terakhir</h3>
        <div v-if="loading" class="h-36 bg-gray-50 animate-pulse rounded-lg"></div>
        <div v-else class="flex items-end gap-1.5 h-36">
          <div
            v-for="day in chart"
            :key="day.date"
            class="flex-1 flex flex-col items-center gap-1.5 group"
          >
            <!-- Tooltip value on hover -->
            <span class="text-[10px] text-gray-300 group-hover:text-gray-500 font-mono transition-colors duration-150 text-center leading-none">
              {{ formatRupiah(day.revenue).replace('Rp\xa0', '') }}
            </span>
            <div
              class="w-full bg-gray-100 group-hover:bg-primary-500 rounded-sm transition-all duration-300 min-h-[3px]"
              :style="{ height: `${Math.max((day.revenue / maxRevenue) * 100, 2)}%` }"
              :title="formatRupiah(day.revenue)"
            ></div>
            <span class="text-[10px] text-gray-400 text-center leading-none">
              {{ day.label.split(',')[0] }}
            </span>
          </div>
        </div>
      </div>

      <!-- Top Selling Drugs -->
      <div class="bg-white border border-gray-100 rounded-xl p-5">
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-5">Obat Terlaris Bulan Ini</h3>
        <div v-if="loading" class="space-y-4">
          <div v-for="i in 5" :key="i" class="h-6 bg-gray-50 animate-pulse rounded"></div>
        </div>
        <div v-else-if="topDrugs.length === 0" class="flex items-center justify-center h-32 text-sm text-gray-300">
          Belum ada data transaksi bulan ini.
        </div>
        <div v-else class="space-y-4">
          <div v-for="(drug, idx) in topDrugs" :key="drug.drug_name" class="flex items-center gap-3">
            <span class="text-xs font-mono text-gray-300 w-4 text-right flex-shrink-0">{{ idx + 1 }}</span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between mb-1">
                <p class="text-sm font-medium text-gray-700 truncate">{{ drug.drug_name }}</p>
                <span class="text-xs text-gray-400 whitespace-nowrap ml-2">{{ drug.total_qty }} pcs</span>
              </div>
              <div class="h-1 bg-gray-100 rounded-full overflow-hidden">
                <div
                  class="h-full bg-primary-400 rounded-full transition-all duration-500"
                  :style="{ width: `${(drug.total_qty / topDrugs[0].total_qty) * 100}%` }"
                ></div>
              </div>
            </div>
            <span class="text-xs font-medium text-gray-500 whitespace-nowrap w-24 text-right flex-shrink-0">
              {{ formatRupiah(drug.total_revenue) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Low Stock Alert Table -->
    <div v-if="!loading && lowStock.length > 0" class="bg-white border border-gray-100 rounded-xl overflow-hidden">
      <!-- Table header -->
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <span class="relative flex h-2 w-2 flex-shrink-0">
            <span class="animate-ping absolute inline-flex h-2 w-2 rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
          </span>
          <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Peringatan Stok Menipis</h3>
        </div>
        <router-link to="/stok" class="text-xs text-gray-400 hover:text-gray-600 transition-colors">
          Lihat Semua →
        </router-link>
      </div>

      <!-- Table -->
      <table class="min-w-full">
        <thead>
          <tr class="border-b border-gray-50">
            <th class="px-5 py-3 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Kode</th>
            <th class="px-5 py-3 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Nama Obat</th>
            <th class="px-5 py-3 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Stok</th>
            <th class="px-5 py-3 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Minimum</th>
            <th class="px-5 py-3 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="drug in lowStock"
            :key="drug.id"
            class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors"
          >
            <td class="px-5 py-3">
              <span class="font-mono text-[11px] text-gray-400">{{ drug.kode_obat }}</span>
            </td>
            <td class="px-5 py-3 text-sm font-medium text-gray-700">{{ drug.name }}</td>
            <td class="px-5 py-3">
              <span class="text-sm font-semibold" :class="drug.stok === 0 ? 'text-red-500' : 'text-amber-500'">
                {{ drug.stok }}
              </span>
            </td>
            <td class="px-5 py-3 text-sm text-gray-400">{{ drug.stok_minimum }}</td>
            <td class="px-5 py-3">
              <span
                class="inline-flex items-center gap-1.5 text-[11px] font-medium"
                :class="drug.stok === 0 ? 'text-red-500' : 'text-amber-500'"
              >
                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="drug.stok === 0 ? 'bg-red-500' : 'bg-amber-400'"></span>
                {{ drug.stok === 0 ? 'Habis' : 'Menipis' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>
