<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/utils/api'
import { formatRupiah, formatDate, isNearExpiry, isExpired } from '@/utils/format'

// ─── State ────────────────────────────────────────────────────────────────────
const batches     = ref([])
const drugs       = ref([])
const suppliers   = ref([])
const summary     = ref({ total: 0, expired: 0, nearExpiry: 0, thisMonth: 0 })
const loading     = ref(true)
const currentPage = ref(1)
const totalPages  = ref(1)

const searchQuery   = ref('')
const filterExpiry  = ref('')
const filterDrug    = ref('')

// Modal
const showModal  = ref(false)
const saving     = ref(false)
const formError  = ref('')
const form       = ref({})

// Drug-batch history drawer
const showHistory     = ref(false)
const historyDrug     = ref(null)
const historyBatches  = ref([])
const loadingHistory  = ref(false)

// ─── Data fetching ─────────────────────────────────────────────────────────────
async function fetchSummary() {
  try {
    const res = await api.get('/batches/summary')
    summary.value = res.data
  } catch (_) {}
}

async function fetchBatches(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/batches', {
      params: {
        page,
        per_page: 15,
        search:       searchQuery.value  || undefined,
        drug_id:      filterDrug.value   || undefined,
        expiry_filter: filterExpiry.value || undefined,
      },
    })
    batches.value     = res.data.data
    currentPage.value = res.data.current_page
    totalPages.value  = res.data.last_page
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

async function fetchDrugs() {
  try {
    // fetch all active drugs for the dropdown (no pagination limit needed for select)
    const res = await api.get('/drugs', { params: { per_page: 200 } })
    drugs.value = res.data.data || []
  } catch (_) {}
}

async function fetchSuppliers() {
  try {
    const res = await api.get('/suppliers')
    suppliers.value = res.data.data || res.data
  } catch (_) {}
}

onMounted(() => {
  fetchSummary()
  fetchBatches()
  fetchDrugs()
  fetchSuppliers()
})

// ─── Modal ─────────────────────────────────────────────────────────────────────
function openAdd() {
  form.value = {
    drug_id:            '',
    supplier_id:        '',
    batch_number:       '',
    quantity_received:  1,
    harga_beli:         0,
    tanggal_kadaluarsa: '',
    tanggal_diterima:   new Date().toISOString().slice(0, 10),
    catatan:            '',
  }
  formError.value = ''
  showModal.value = true
}

async function saveBatch() {
  saving.value = true
  formError.value = ''
  try {
    await api.post('/batches', form.value)
    showModal.value = false
    await fetchBatches(currentPage.value)
    await fetchSummary()
  } catch (e) {
    const errs = e.response?.data?.errors
    formError.value = errs
      ? Object.values(errs).flat().join(' | ')
      : (e.response?.data?.message || 'Terjadi kesalahan.')
  } finally {
    saving.value = false
  }
}

// ─── History drawer ────────────────────────────────────────────────────────────
async function openHistory(drug) {
  historyDrug.value    = drug
  historyBatches.value = []
  showHistory.value    = true
  loadingHistory.value = true
  try {
    const res = await api.get(`/drugs/${drug.drug.id}/batches`)
    historyBatches.value = res.data.batches
  } catch (_) {}
  finally { loadingHistory.value = false }
}

// ─── Search debounce ───────────────────────────────────────────────────────────
let searchTimeout = null
function onSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchBatches(1), 400)
}

// ─── Helpers ───────────────────────────────────────────────────────────────────
function expiryClass(date) {
  if (!date) return 'bg-gray-100 text-gray-500'
  if (isExpired(date))    return 'bg-red-100 text-red-700'
  if (isNearExpiry(date, 90)) return 'bg-amber-100 text-amber-700'
  return 'bg-green-100 text-green-700'
}

function stockPct(batch) {
  if (!batch.quantity_received) return 0
  return Math.round((batch.quantity_remaining / batch.quantity_received) * 100)
}

const selectedDrugName = computed(() => {
  if (!form.value.drug_id) return ''
  return drugs.value.find(d => d.id == form.value.drug_id)?.name || ''
})
</script>

<template>
  <div class="space-y-5">

    <!-- Header -->
    <div class="flex flex-wrap gap-3 items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Manajemen Batch</h2>
        <p class="text-sm text-gray-500">Catat penerimaan stok per batch / lot obat</p>
      </div>
      <button @click="openAdd" class="btn-primary">+ Terima Batch Baru</button>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-xl">📦</div>
        <div>
          <p class="text-xs text-gray-500">Total Batch</p>
          <p class="text-2xl font-bold text-gray-800">{{ summary.total }}</p>
        </div>
      </div>
      <div class="card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center text-xl">📅</div>
        <div>
          <p class="text-xs text-gray-500">Bulan Ini</p>
          <p class="text-2xl font-bold text-green-700">{{ summary.thisMonth }}</p>
        </div>
      </div>
      <div class="card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center text-xl">⏰</div>
        <div>
          <p class="text-xs text-gray-500">Akan Kadaluarsa</p>
          <p class="text-2xl font-bold text-amber-600">{{ summary.nearExpiry }}</p>
        </div>
      </div>
      <div class="card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">⚠️</div>
        <div>
          <p class="text-xs text-gray-500">Sudah Kadaluarsa</p>
          <p class="text-2xl font-bold text-red-600">{{ summary.expired }}</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card p-4 flex flex-wrap gap-3">
      <div class="relative flex-1 min-w-48">
        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">🔍</span>
        <input v-model="searchQuery" @input="onSearch" type="text"
          placeholder="Cari nama obat / nomor batch..."
          class="form-input pl-9" />
      </div>
      <select v-model="filterDrug" @change="fetchBatches(1)" class="form-select w-52">
        <option value="">Semua Obat</option>
        <option v-for="d in drugs" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
      <select v-model="filterExpiry" @change="fetchBatches(1)" class="form-select w-44">
        <option value="">Semua Kadaluarsa</option>
        <option value="near">Akan Kadaluarsa (90 hari)</option>
        <option value="expired">Sudah Kadaluarsa</option>
      </select>
    </div>

    <!-- Batch table -->
    <div class="card">
      <div class="table-wrapper rounded-xl border-0">
        <table class="table">
          <thead>
            <tr>
              <th>Nomor Batch</th>
              <th>Nama Obat</th>
              <th>Rak</th>
              <th>Supplier</th>
              <th>Tgl Terima</th>
              <th>Tgl Kadaluarsa</th>
              <th>Harga Beli</th>
              <th>Sisa / Terima</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading" v-for="i in 8" :key="i">
              <td colspan="9"><div class="h-4 bg-gray-100 animate-pulse rounded"></div></td>
            </tr>
            <tr v-else-if="batches.length === 0">
              <td colspan="9" class="text-center py-12 text-gray-400">
                <div class="text-4xl mb-2">📦</div>
                <p>Belum ada batch tercatat.</p>
              </td>
            </tr>
            <tr v-else v-for="b in batches" :key="b.id">
              <td>
                <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ b.batch_number }}</span>
              </td>
              <td>
                <p class="font-medium text-gray-900">{{ b.drug?.name }}</p>
                <p class="text-xs text-gray-400">{{ b.drug?.kode_obat }}</p>
              </td>
              <td>
                <span v-if="b.drug?.lokasi_rak" class="font-medium text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded border border-amber-200">
                  {{ b.drug.lokasi_rak }}
                </span>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>
              <td class="text-sm text-gray-600">{{ b.supplier?.name || '—' }}</td>
              <td class="text-sm">{{ formatDate(b.tanggal_diterima) }}</td>
              <td>
                <span v-if="b.tanggal_kadaluarsa"
                  class="text-xs font-medium px-2 py-0.5 rounded-full"
                  :class="expiryClass(b.tanggal_kadaluarsa)">
                  {{ formatDate(b.tanggal_kadaluarsa) }}
                </span>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
              <td class="font-semibold text-primary-700">{{ formatRupiah(b.harga_beli) }}</td>
              <td>
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden w-16">
                    <div class="h-full rounded-full transition-all"
                      :class="stockPct(b) > 50 ? 'bg-green-500' : stockPct(b) > 20 ? 'bg-amber-400' : 'bg-red-500'"
                      :style="{ width: stockPct(b) + '%' }"></div>
                  </div>
                  <span class="text-xs font-semibold whitespace-nowrap">
                    {{ b.quantity_remaining }} / {{ b.quantity_received }}
                    <span class="text-gray-400 font-normal">{{ b.drug?.satuan }}</span>
                  </span>
                </div>
              </td>
              <td>
                <button @click="openHistory(b)"
                  class="px-2 py-1 text-xs bg-blue-50 text-blue-700 rounded hover:bg-blue-100 font-medium"
                  title="Lihat semua batch obat ini">📋 Riwayat</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm">
        <span class="text-gray-500">Halaman {{ currentPage }} dari {{ totalPages }}</span>
        <div class="flex gap-2">
          <button @click="fetchBatches(currentPage - 1)" :disabled="currentPage === 1"
            class="btn-secondary px-3 py-1 text-xs">← Sebelumnya</button>
          <button @click="fetchBatches(currentPage + 1)" :disabled="currentPage === totalPages"
            class="btn-secondary px-3 py-1 text-xs">Berikutnya →</button>
        </div>
      </div>
    </div>

    <!-- ── Add Batch Modal ──────────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto">
          <div class="sticky top-0 bg-primary-700 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-white font-semibold">📦 Terima Batch Baru</h3>
            <button @click="showModal = false" class="text-primary-200 hover:text-white text-xl">✕</button>
          </div>
          <div class="p-6 space-y-4">
            <div v-if="formError" class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
              {{ formError }}
            </div>

            <div class="grid grid-cols-2 gap-4">
              <!-- Drug -->
              <div class="col-span-2">
                <label class="form-label">Obat *</label>
                <select v-model="form.drug_id" class="form-select">
                  <option value="">Pilih Obat</option>
                  <option v-for="d in drugs" :key="d.id" :value="d.id">
                    {{ d.kode_obat }} — {{ d.name }}
                  </option>
                </select>
                <p v-if="selectedDrugName" class="text-xs text-primary-600 mt-1">
                  ✓ {{ selectedDrugName }}
                </p>
              </div>

              <!-- Batch number -->
              <div>
                <label class="form-label">Nomor Batch *</label>
                <input v-model="form.batch_number" type="text" class="form-input"
                  placeholder="cth: BTH-2024-001" />
              </div>

              <!-- Supplier -->
              <div>
                <label class="form-label">Supplier</label>
                <select v-model="form.supplier_id" class="form-select">
                  <option value="">Pilih Supplier</option>
                  <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>

              <!-- Qty -->
              <div>
                <label class="form-label">Jumlah Diterima *</label>
                <input v-model.number="form.quantity_received" type="number" min="1" class="form-input" />
              </div>

              <!-- Harga beli -->
              <div>
                <label class="form-label">Harga Beli per Satuan (Rp) *</label>
                <input v-model.number="form.harga_beli" type="number" min="0" class="form-input" />
              </div>

              <!-- Tanggal diterima -->
              <div>
                <label class="form-label">Tanggal Diterima *</label>
                <input v-model="form.tanggal_diterima" type="date" class="form-input" />
              </div>

              <!-- Tanggal kadaluarsa -->
              <div>
                <label class="form-label">Tanggal Kadaluarsa</label>
                <input v-model="form.tanggal_kadaluarsa" type="date" class="form-input" />
              </div>

              <!-- Catatan -->
              <div class="col-span-2">
                <label class="form-label">Catatan</label>
                <textarea v-model="form.catatan" class="form-input" rows="2"
                  placeholder="Catatan penerimaan batch..."></textarea>
              </div>
            </div>

            <div class="flex gap-3 pt-2">
              <button @click="showModal = false" class="btn-secondary flex-1 justify-center">Batal</button>
              <button @click="saveBatch" :disabled="saving" class="btn-primary flex-1 justify-center">
                {{ saving ? 'Menyimpan...' : '✓ Simpan Batch' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Batch History Drawer ──────────────────────────────────────────────── -->
    <Transition name="slide-right">
      <div v-if="showHistory" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/40" @click="showHistory = false"></div>
        <div class="relative w-full max-w-lg bg-white h-full flex flex-col shadow-2xl">
          <!-- Header -->
          <div class="bg-blue-700 px-6 py-4 flex items-center justify-between">
            <div>
              <h3 class="text-white font-semibold">📋 Riwayat Batch</h3>
              <p class="text-blue-200 text-xs mt-0.5">{{ historyDrug?.drug?.name }}</p>
            </div>
            <button @click="showHistory = false" class="text-blue-200 hover:text-white text-xl">✕</button>
          </div>

          <!-- Drug summary bar -->
          <div v-if="historyDrug" class="bg-blue-50 px-6 py-3 border-b border-blue-100 flex gap-4 text-sm">
            <span class="text-blue-800">Kode: <strong>{{ historyDrug.drug?.kode_obat }}</strong></span>
            <span v-if="historyDrug.drug?.lokasi_rak" class="text-amber-800">Rak: <strong>{{ historyDrug.drug?.lokasi_rak }}</strong></span>
          </div>

          <!-- Batch list -->
          <div class="flex-1 overflow-y-auto p-5 space-y-3">
            <div v-if="loadingHistory" v-for="i in 4" :key="i"
              class="h-20 bg-gray-100 animate-pulse rounded-xl"></div>

            <div v-else-if="historyBatches.length === 0"
              class="text-center py-16 text-gray-400">
              <div class="text-4xl mb-2">📭</div>
              <p class="text-sm">Belum ada batch untuk obat ini.</p>
            </div>

            <div v-else v-for="b in historyBatches" :key="b.id"
              class="card p-4 space-y-2">
              <div class="flex items-start justify-between">
                <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded font-semibold">
                  {{ b.batch_number }}
                </span>
                <span class="text-xs px-2 py-0.5 rounded-full"
                  :class="expiryClass(b.tanggal_kadaluarsa)">
                  {{ b.tanggal_kadaluarsa ? formatDate(b.tanggal_kadaluarsa) : 'Tdk ada exp.' }}
                </span>
              </div>
              <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-600">
                <span>Terima: <strong>{{ formatDate(b.tanggal_diterima) }}</strong></span>
                <span>Harga beli: <strong>{{ formatRupiah(b.harga_beli) }}</strong></span>
                <span>Diterima: <strong>{{ b.quantity_received }}</strong></span>
                <span>Sisa: <strong :class="b.quantity_remaining === 0 ? 'text-red-600' : 'text-green-600'">
                  {{ b.quantity_remaining }}
                </strong></span>
                <span v-if="b.supplier" class="col-span-2">Supplier: <strong>{{ b.supplier.name }}</strong></span>
                <span v-if="b.received_by" class="col-span-2">Diterima oleh: <strong>{{ b.received_by?.name }}</strong></span>
              </div>
              <div v-if="b.catatan" class="text-xs text-gray-500 italic border-t pt-2">
                {{ b.catatan }}
              </div>
              <!-- stock bar -->
              <div class="flex items-center gap-2 pt-1">
                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full"
                    :class="stockPct(b) > 50 ? 'bg-green-500' : stockPct(b) > 20 ? 'bg-amber-400' : 'bg-red-500'"
                    :style="{ width: stockPct(b) + '%' }"></div>
                </div>
                <span class="text-xs text-gray-500">{{ stockPct(b) }}% tersisa</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.slide-right-enter-active, .slide-right-leave-active { transition: all 0.25s ease; }
.slide-right-enter-from .relative, .slide-right-leave-to .relative { transform: translateX(100%); }
.slide-right-enter-from, .slide-right-leave-to { opacity: 0; }
</style>
