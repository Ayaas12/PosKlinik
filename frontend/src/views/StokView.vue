<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/utils/api'
import { formatRupiah, formatDate, isNearExpiry, isExpired } from '@/utils/format'

// ─── Drug list ────────────────────────────────────────────────────────────────
const drugs       = ref([])
const categories  = ref([])
const loading     = ref(true)
const searchQuery = ref('')
const filterCategory = ref('')
const filterStok  = ref('')
const currentPage = ref(1)
const totalPages  = ref(1)

// ─── Modal (add / edit) ───────────────────────────────────────────────────────
const showModal  = ref(false)
const modalMode  = ref('add')  // add | edit | adjust
const form       = ref({})
const saving     = ref(false)
const formError  = ref('')

// Adjust stock
const adjustQty  = ref(1)
const adjustType = ref('masuk')
const adjustNote = ref('')

// ─── Movement history drawer ──────────────────────────────────────────────────
const showMovements     = ref(false)
const movementsDrug     = ref(null)
const movements         = ref([])
const movementsPage     = ref(1)
const movementsTotalPgs = ref(1)
const loadingMovements  = ref(false)

// ─── Drug Unit Pricing ────────────────────────────────────────────────────────
const drugUnits       = ref([])   // units for the drug currently being edited
const loadingUnits    = ref(false)
const showUnitForm    = ref(false)
const unitFormMode    = ref('add') // add | edit
const editingUnit     = ref(null)
const unitForm        = ref({ label: '', satuan: 'strip', konversi: 1, harga_jual: '', is_default: false })
const unitFormError   = ref('')
const savingUnit      = ref(false)
// ─── Low-stock summary ─────────────────────────────────────────────────────────
const lowStockList  = ref([])
const showLowStock  = ref(false)

// ─── Fetch helpers ─────────────────────────────────────────────────────────────
async function fetchDrugs(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/drugs', {
      params: {
        page,
        per_page: 15,
        search:       searchQuery.value   || undefined,
        category_id:  filterCategory.value || undefined,
        stok_filter:  filterStok.value     || undefined,
      },
    })
    drugs.value       = res.data.data
    currentPage.value = res.data.current_page
    totalPages.value  = res.data.last_page
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

async function fetchCategories() {
  try {
    const res = await api.get('/categories')
    categories.value = res.data.data || res.data
  } catch (_) {}
}

async function fetchLowStock() {
  try {
    const res = await api.get('/drugs', { params: { low_stock: 1, per_page: 100 } })
    lowStockList.value = res.data.data || []
  } catch (_) {}
}

onMounted(() => { fetchDrugs(); fetchCategories(); fetchLowStock() })

// ─── Movement history drawer ──────────────────────────────────────────────────
async function openMovements(drug, page = 1) {
  movementsDrug.value = drug
  showMovements.value = true
  loadingMovements.value = true
  movements.value = []
  try {
    const res = await api.get(`/drugs/${drug.id}/movements`, { params: { page, per_page: 15 } })
    movements.value         = res.data.data
    movementsPage.value     = res.data.current_page
    movementsTotalPgs.value = res.data.last_page
  } catch (_) {}
  finally { loadingMovements.value = false }
}

// ─── Drug CRUD modals ──────────────────────────────────────────────────────────
function openAdd() {
  form.value = {
    name: '', kode_obat: '', category_id: '', harga_beli: '', harga_jual: '',
    stok: '', stok_minimum: 10, satuan: 'strip', tanggal_kadaluarsa: '', lokasi_rak: '', description: '',
  }
  formError.value = ''
  modalMode.value = 'add'
  showModal.value = true
}

function openEdit(drug) {
  form.value = { ...drug, category_id: drug.category_id ?? '' }
  formError.value = ''
  modalMode.value = 'edit'
  showModal.value = true
  // Load units for this drug
  drugUnits.value = []
  showUnitForm.value = false
  if (drug.units) {
    drugUnits.value = [...drug.units]
  } else {
    loadUnits(drug.id)
  }
}

function openAdjust(drug) {
  form.value   = drug
  adjustQty.value  = 1
  adjustType.value = 'masuk'
  adjustNote.value = ''
  formError.value  = ''
  modalMode.value  = 'adjust'
  showModal.value  = true
}

async function saveForm() {
  saving.value    = true
  formError.value = ''
  try {
    if (modalMode.value === 'add') {
      await api.post('/drugs', form.value)
    } else {
      await api.put(`/drugs/${form.value.id}`, form.value)
    }
    showModal.value = false
    drugUnits.value = []
    showUnitForm.value = false
    await fetchDrugs(currentPage.value)
    await fetchLowStock()
  } catch (e) {
    const errs = e.response?.data?.errors
    formError.value = errs
      ? Object.values(errs).flat().join(' | ')
      : (e.response?.data?.message || 'Terjadi kesalahan.')
  } finally {
    saving.value = false
  }
}

async function saveAdjust() {
  saving.value    = true
  formError.value = ''
  try {
    await api.post(`/drugs/${form.value.id}/adjust-stock`, {
      type:     adjustType.value,
      quantity: Number(adjustQty.value),
      catatan:  adjustNote.value,
    })
    showModal.value = false
    await fetchDrugs(currentPage.value)
    await fetchLowStock()
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal menyesuaikan stok.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(drug) {
  try {
    await api.put(`/drugs/${drug.id}`, { ...drug, is_active: !drug.is_active })
    drug.is_active = !drug.is_active
  } catch (_) {}
}

// ─── Search debounce ───────────────────────────────────────────────────────────
let searchTimeout = null
function onSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchDrugs(1), 400)
}

// ─── Helpers ───────────────────────────────────────────────────────────────────
const satuanOptions = ['strip', 'kapsul', 'botol', 'sachet', 'tube', 'ampul', 'pcs']

// ─── Drug Unit Management helpers ─────────────────────────────────────────────
async function loadUnits(drugId) {
  loadingUnits.value = true
  try {
    const res = await api.get(`/drugs/${drugId}/units`)
    drugUnits.value = res.data
  } catch (_) {}
  finally { loadingUnits.value = false }
}

function openAddUnit() {
  unitForm.value = { label: '', satuan: satuanOptions[0], konversi: 1, harga_jual: '', is_default: false }
  unitFormError.value = ''
  unitFormMode.value = 'add'
  editingUnit.value = null
  showUnitForm.value = true
}

function openEditUnit(unit) {
  unitForm.value = { ...unit }
  unitFormError.value = ''
  unitFormMode.value = 'edit'
  editingUnit.value = unit
  showUnitForm.value = true
}

async function saveUnit() {
  if (!form.value.id) return // only available in edit mode
  savingUnit.value = true
  unitFormError.value = ''
  try {
    if (unitFormMode.value === 'add') {
      const res = await api.post(`/drugs/${form.value.id}/units`, unitForm.value)
      drugUnits.value.push(res.data.unit)
    } else {
      const res = await api.put(`/drugs/${form.value.id}/units/${editingUnit.value.id}`, unitForm.value)
      const idx = drugUnits.value.findIndex(u => u.id === editingUnit.value.id)
      if (idx >= 0) drugUnits.value[idx] = res.data.unit
    }
    showUnitForm.value = false
    // Refresh catalog data
    await fetchDrugs(currentPage.value)
  } catch (e) {
    const errs = e.response?.data?.errors
    unitFormError.value = errs
      ? Object.values(errs).flat().join(' | ')
      : (e.response?.data?.message || 'Gagal menyimpan unit.')
  } finally {
    savingUnit.value = false
  }
}

async function deleteUnit(unit) {
  if (!confirm(`Hapus unit "${unit.label}"?`)) return
  try {
    await api.delete(`/drugs/${form.value.id}/units/${unit.id}`)
    drugUnits.value = drugUnits.value.filter(u => u.id !== unit.id)
    await fetchDrugs(currentPage.value)
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menghapus unit.')
  }
}

function movementTypeLabel(type) {
  return { masuk: 'Masuk', keluar: 'Keluar', penyesuaian: 'Penyesuaian', retur: 'Retur' }[type] ?? type
}
function movementTypeClass(type) {
  if (type === 'masuk')  return 'bg-green-100 text-green-700'
  if (type === 'keluar') return 'bg-red-100 text-red-700'
  return 'bg-gray-100 text-gray-600'
}

const lowStockCount = computed(() => lowStockList.value.length)
</script>

<template>
  <div class="space-y-4">

    <!-- Header -->
    <div class="flex flex-wrap gap-3 items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Manajemen Stok</h2>
        <p class="text-sm text-gray-500">Monitor stok saat ini, pergerakan, dan peringatan menipis</p>
      </div>
      <button @click="openAdd" class="btn-primary">+ Tambah Obat</button>
    </div>

    <!-- Low-stock alert banner -->
    <div v-if="lowStockCount > 0"
      class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <span class="text-amber-500 text-lg">⚠️</span>
        <span class="text-sm font-medium text-amber-800">
          {{ lowStockCount }} obat stok menipis atau habis
        </span>
      </div>
      <button @click="showLowStock = !showLowStock"
        class="text-xs text-amber-700 font-semibold underline underline-offset-2 hover:text-amber-900">
        {{ showLowStock ? 'Sembunyikan' : 'Lihat daftar' }}
      </button>
    </div>

    <!-- Low-stock expandable list -->
    <Transition name="expand">
      <div v-if="showLowStock && lowStockList.length" class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
          <span class="text-amber-500">⚠️</span>
          <h3 class="text-sm font-semibold text-gray-700">Daftar Stok Menipis / Habis</h3>
        </div>
        <div class="table-wrapper rounded-none rounded-b-xl border-0">
          <table class="table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Obat</th>
                <th>Stok Saat Ini</th>
                <th>Stok Minimum</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="drug in lowStockList" :key="drug.id">
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
                <td>
                  <button @click="openAdjust(drug)"
                    class="px-2 py-1 text-xs bg-blue-50 text-blue-700 rounded hover:bg-blue-100 font-medium">
                    📦 Sesuaikan
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Transition>

    <!-- Filters -->
    <div class="card p-4 flex flex-wrap gap-3">
      <div class="relative flex-1 min-w-48">
        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">🔍</span>
        <input v-model="searchQuery" @input="onSearch" type="text"
          placeholder="Cari nama / kode obat..."
          class="form-input pl-9" />
      </div>
      <select v-model="filterCategory" @change="fetchDrugs(1)" class="form-select w-44">
        <option value="">Semua Kategori</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="filterStok" @change="fetchDrugs(1)" class="form-select w-44">
        <option value="">Semua Stok</option>
        <option value="low">Stok Menipis</option>
        <option value="empty">Stok Habis</option>
      </select>
    </div>

    <!-- Drug table -->
    <div class="card">
      <div class="table-wrapper rounded-xl border-0">
        <table class="table">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama Obat</th>
              <th>Kategori</th>
              <th>Rak</th>
              <th>Harga Jual</th>
              <th>Stok</th>
              <th>Kadaluarsa</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading" v-for="i in 8" :key="i">
              <td colspan="9"><div class="h-4 bg-gray-100 animate-pulse rounded"></div></td>
            </tr>
            <tr v-else-if="drugs.length === 0">
              <td colspan="9" class="text-center py-12 text-gray-400">
                <div class="text-4xl mb-2">💊</div>
                <p>Tidak ada obat ditemukan.</p>
              </td>
            </tr>
            <tr v-else v-for="drug in drugs" :key="drug.id">
              <td><span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ drug.kode_obat }}</span></td>
              <td>
                <p class="font-medium text-gray-900">{{ drug.name }}</p>
                <p class="text-xs text-gray-400">{{ drug.satuan }}</p>
              </td>
              <td><span class="badge-blue">{{ drug.category?.name || '—' }}</span></td>
              <td>
                <span v-if="drug.lokasi_rak" class="font-medium text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded border border-amber-200">
                  {{ drug.lokasi_rak }}
                </span>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>
              <td class="font-semibold text-primary-700">{{ formatRupiah(drug.harga_jual) }}</td>
              <td>
                <span class="font-bold"
                  :class="drug.stok === 0 ? 'text-red-600' : drug.stok <= drug.stok_minimum ? 'text-amber-600' : 'text-green-600'">
                  {{ drug.stok }}
                </span>
                <span class="text-gray-400 text-xs ml-1">/ min {{ drug.stok_minimum }}</span>
              </td>
              <td>
                <span v-if="drug.tanggal_kadaluarsa"
                  class="text-xs font-medium px-2 py-0.5 rounded-full"
                  :class="isExpired(drug.tanggal_kadaluarsa)
                    ? 'bg-red-100 text-red-700'
                    : isNearExpiry(drug.tanggal_kadaluarsa)
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-gray-100 text-gray-600'">
                  {{ formatDate(drug.tanggal_kadaluarsa) }}
                  {{ isExpired(drug.tanggal_kadaluarsa) ? '⚠️' : isNearExpiry(drug.tanggal_kadaluarsa) ? '⏰' : '' }}
                </span>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>
              <td>
                <span :class="drug.is_active ? 'badge-green' : 'badge-red'">
                  {{ drug.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td>
                <div class="flex gap-1">
                  <button @click="openMovements(drug)"
                    class="px-2 py-1 text-xs bg-purple-50 text-purple-700 rounded hover:bg-purple-100 font-medium"
                    title="Riwayat Pergerakan Stok">📈</button>
                  <button @click="openAdjust(drug)"
                    class="px-2 py-1 text-xs bg-blue-50 text-blue-700 rounded hover:bg-blue-100 font-medium"
                    title="Sesuaikan Stok">📦</button>
                  <button @click="openEdit(drug)"
                    class="px-2 py-1 text-xs bg-amber-50 text-amber-700 rounded hover:bg-amber-100 font-medium"
                    title="Edit">✏️</button>
                  <button @click="toggleActive(drug)"
                    class="px-2 py-1 text-xs rounded font-medium"
                    :class="drug.is_active ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-green-50 text-green-700 hover:bg-green-100'"
                    :title="drug.is_active ? 'Nonaktifkan' : 'Aktifkan'">
                    {{ drug.is_active ? '🚫' : '✓' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm">
        <span class="text-gray-500">Halaman {{ currentPage }} dari {{ totalPages }}</span>
        <div class="flex gap-2">
          <button @click="fetchDrugs(currentPage - 1)" :disabled="currentPage === 1"
            class="btn-secondary px-3 py-1 text-xs">← Sebelumnya</button>
          <button @click="fetchDrugs(currentPage + 1)" :disabled="currentPage === totalPages"
            class="btn-secondary px-3 py-1 text-xs">Berikutnya →</button>
        </div>
      </div>
    </div>

    <!-- ── Add / Edit Modal ────────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showModal && modalMode !== 'adjust'"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-primary-700 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-white font-semibold">{{ modalMode === 'add' ? '+ Tambah Obat' : '✏️ Edit Obat' }}</h3>
            <button @click="showModal = false" class="text-primary-200 hover:text-white text-xl">✕</button>
          </div>
          <div class="p-6 space-y-4">
            <div v-if="formError" class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
              {{ formError }}
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Nama Obat *</label>
                <input v-model="form.name" type="text" class="form-input" placeholder="Nama lengkap obat" />
              </div>
              <div>
                <label class="form-label">Kode Obat *</label>
                <input v-model="form.kode_obat" type="text" class="form-input" placeholder="OBT-001" />
              </div>
              <div>
                <label class="form-label">Kategori *</label>
                <select v-model="form.category_id" class="form-select">
                  <option value="">Pilih Kategori</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div>
                <label class="form-label">Satuan *</label>
                <select v-model="form.satuan" class="form-select">
                  <option v-for="s in satuanOptions" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>
              <div>
                <label class="form-label">Harga Beli (Rp)</label>
                <input v-model.number="form.harga_beli" type="number" min="0" class="form-input" />
              </div>
              <div>
                <label class="form-label">Harga Jual (Rp) *</label>
                <input v-model.number="form.harga_jual" type="number" min="0" class="form-input" />
              </div>
              <div>
                <label class="form-label">Stok Awal</label>
                <input v-model.number="form.stok" type="number" min="0" class="form-input" />
              </div>
              <div>
                <label class="form-label">Stok Minimum</label>
                <input v-model.number="form.stok_minimum" type="number" min="0" class="form-input" />
              </div>
              <div>
                <label class="form-label">Tanggal Kadaluarsa</label>
                <input v-model="form.tanggal_kadaluarsa" type="date" class="form-input" />
              </div>
              <div>
                <label class="form-label">Lokasi Rak</label>
                <input v-model="form.lokasi_rak" type="text" class="form-input" placeholder="cth: Rak A-01" />
              </div>
              <div class="col-span-2">
                <label class="form-label">Keterangan</label>
                <textarea v-model="form.description" class="form-input" rows="2"
                  placeholder="Dosis, indikasi, dll."></textarea>
              </div>
            </div>

            <!-- ── Variasi Harga / Satuan (edit mode only) ─────────────────── -->
            <div v-if="modalMode === 'edit'" class="border border-indigo-200 rounded-xl overflow-hidden">
              <div class="bg-indigo-50 px-4 py-2.5 flex items-center justify-between border-b border-indigo-100">
                <div class="flex items-center gap-2">
                  <span class="text-indigo-500">🏷️</span>
                  <span class="font-semibold text-indigo-800 text-sm">Variasi Harga / Satuan</span>
                  <span class="text-[10px] px-1.5 py-0.5 bg-indigo-100 text-indigo-600 rounded-full font-medium">{{ drugUnits.length }} unit</span>
                </div>
                <button @click="openAddUnit" type="button"
                  class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded-lg font-medium transition-colors">
                  + Tambah Unit
                </button>
              </div>

              <!-- Loading -->
              <div v-if="loadingUnits" class="p-4 text-xs text-gray-400 text-center animate-pulse">Memuat unit...</div>

              <!-- No units -->
              <div v-else-if="drugUnits.length === 0 && !showUnitForm" class="px-4 py-3 text-xs text-gray-400 text-center">
                Belum ada variasi harga. Klik "+ Tambah Unit" untuk menambahkan.
              </div>

              <!-- Unit list -->
              <div v-if="drugUnits.length > 0" class="divide-y divide-gray-100">
                <div v-for="u in drugUnits" :key="u.id"
                  class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50 transition-colors">
                  <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div>
                      <p class="text-sm font-semibold text-gray-800">{{ u.label }}</p>
                      <p class="text-[10px] text-gray-400">
                        {{ u.satuan }}
                        <span v-if="u.konversi > 1"> · isi {{ u.konversi }} satuan dasar</span>
                        <span v-if="u.is_default" class="ml-1 text-green-600 font-medium">★ Default</span>
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center gap-3 flex-shrink-0">
                    <span class="font-bold text-indigo-700 text-sm whitespace-nowrap">{{ u.harga_jual ? Number(u.harga_jual).toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }) : 'Rp 0' }}</span>
                    <div class="flex gap-1">
                      <button @click="openEditUnit(u)" type="button"
                        class="text-xs px-2 py-1 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded font-medium">✏️</button>
                      <button @click="deleteUnit(u)" type="button"
                        class="text-xs px-2 py-1 bg-red-50 text-red-600 hover:bg-red-100 rounded font-medium">✕</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Inline unit form -->
              <Transition name="expand">
                <div v-if="showUnitForm" class="border-t border-indigo-100 bg-indigo-50/50 p-4 space-y-3">
                  <p class="text-xs font-semibold text-indigo-700">{{ unitFormMode === 'add' ? 'Tambah Unit Baru' : 'Edit Unit' }}</p>
                  <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                      <label class="form-label text-xs">Label Tampilan *</label>
                      <input v-model="unitForm.label" type="text" class="form-input text-sm"
                        placeholder="cth: Strip, Box (10 Strip), Botol 60ml" />
                    </div>
                    <div>
                      <label class="form-label text-xs">Satuan *</label>
                      <select v-model="unitForm.satuan" class="form-select text-sm">
                        <option v-for="s in satuanOptions" :key="s" :value="s">{{ s }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="form-label text-xs">Isi (konversi ke satuan dasar) *</label>
                      <input v-model.number="unitForm.konversi" type="number" min="1" class="form-input text-sm"
                        placeholder="1" />
                    </div>
                    <div class="col-span-2">
                      <label class="form-label text-xs">Harga Jual (Rp) *</label>
                      <input v-model.number="unitForm.harga_jual" type="number" min="0" class="form-input text-sm" />
                    </div>
                    <div class="col-span-2 flex items-center gap-2">
                      <input v-model="unitForm.is_default" type="checkbox" id="unit-default" class="rounded" />
                      <label for="unit-default" class="text-xs text-gray-600">Jadikan unit default di kasir</label>
                    </div>
                  </div>
                  <div v-if="unitFormError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ unitFormError }}</div>
                  <div class="flex gap-2">
                    <button @click="showUnitForm = false" type="button" class="btn-secondary flex-1 justify-center text-xs py-1.5">Batal</button>
                    <button @click="saveUnit" :disabled="savingUnit" type="button" class="btn-primary flex-1 justify-center text-xs py-1.5">
                      {{ savingUnit ? 'Menyimpan...' : 'Simpan Unit' }}
                    </button>
                  </div>
                </div>
              </Transition>
            </div>

            <div class="flex gap-3 pt-2">
              <button @click="showModal = false" class="btn-secondary flex-1 justify-center">Batal</button>
              <button @click="saveForm" :disabled="saving" class="btn-primary flex-1 justify-center">
                {{ saving ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Adjust Stock Modal ─────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showModal && modalMode === 'adjust'"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="bg-blue-700 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-white font-semibold">📦 Sesuaikan Stok</h3>
            <button @click="showModal = false" class="text-blue-200 hover:text-white text-xl">✕</button>
          </div>
          <div class="p-6 space-y-4">
            <div class="bg-blue-50 rounded-xl p-4">
              <p class="font-semibold text-blue-900">{{ form.name }}</p>
              <p class="text-sm text-blue-600 mt-1">
                Stok saat ini: <strong>{{ form.stok }} {{ form.satuan }}</strong>
              </p>
            </div>
            <div>
              <label class="form-label">Jenis Penyesuaian</label>
              <div class="grid grid-cols-2 gap-2">
                <button @click="adjustType = 'masuk'"
                  class="py-2 rounded-lg text-sm font-medium border-2 transition-all"
                  :class="adjustType === 'masuk' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200 text-gray-600'">
                  ➕ Stok Masuk
                </button>
                <button @click="adjustType = 'keluar'"
                  class="py-2 rounded-lg text-sm font-medium border-2 transition-all"
                  :class="adjustType === 'keluar' ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-200 text-gray-600'">
                  ➖ Stok Keluar
                </button>
              </div>
            </div>
            <div>
              <label class="form-label">Jumlah *</label>
              <input v-model.number="adjustQty" type="number" min="1" class="form-input text-lg font-bold" />
            </div>
            <div>
              <label class="form-label">Keterangan</label>
              <input v-model="adjustNote" type="text" class="form-input"
                placeholder="Alasan penyesuaian stok..." />
            </div>
            <div v-if="formError" class="text-sm text-red-600 bg-red-50 p-3 rounded-lg">{{ formError }}</div>
            <div class="flex gap-3">
              <button @click="showModal = false" class="btn-secondary flex-1 justify-center">Batal</button>
              <button @click="saveAdjust" :disabled="saving || adjustQty < 1"
                class="btn-primary flex-1 justify-center">
                {{ saving ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Movement History Drawer ────────────────────────────────────────── -->
    <Transition name="slide-right">
      <div v-if="showMovements" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/40" @click="showMovements = false"></div>
        <div class="relative w-full max-w-lg bg-white h-full flex flex-col shadow-2xl">
          <!-- Drawer header -->
          <div class="bg-purple-700 px-6 py-4 flex items-center justify-between">
            <div>
              <h3 class="text-white font-semibold">📈 Riwayat Pergerakan Stok</h3>
              <p class="text-purple-200 text-xs mt-0.5">{{ movementsDrug?.name }}</p>
            </div>
            <button @click="showMovements = false" class="text-purple-200 hover:text-white text-xl">✕</button>
          </div>

          <!-- Drug stats bar -->
          <div v-if="movementsDrug" class="bg-purple-50 px-6 py-3 border-b border-purple-100 flex gap-6 text-sm">
            <span class="text-purple-800">
              Stok: <strong :class="movementsDrug.stok === 0 ? 'text-red-600' : movementsDrug.stok <= movementsDrug.stok_minimum ? 'text-amber-600' : 'text-green-700'">
                {{ movementsDrug.stok }} {{ movementsDrug.satuan }}
              </strong>
            </span>
            <span class="text-purple-600">Min: {{ movementsDrug.stok_minimum }}</span>
          </div>

          <!-- Movements list -->
          <div class="flex-1 overflow-y-auto p-5 space-y-2">
            <div v-if="loadingMovements" v-for="i in 5" :key="i"
              class="h-16 bg-gray-100 animate-pulse rounded-xl"></div>

            <div v-else-if="movements.length === 0"
              class="text-center py-16 text-gray-400">
              <div class="text-4xl mb-2">📭</div>
              <p class="text-sm">Belum ada riwayat pergerakan stok.</p>
            </div>

            <div v-else v-for="m in movements" :key="m.id"
              class="flex items-start gap-3 card p-3">
              <!-- type badge -->
              <span class="mt-0.5 px-2 py-0.5 rounded text-xs font-semibold whitespace-nowrap"
                :class="movementTypeClass(m.type)">
                {{ movementTypeLabel(m.type) }}
              </span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-sm font-bold"
                    :class="m.quantity > 0 ? 'text-green-700' : 'text-red-600'">
                    {{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}
                  </span>
                  <span class="text-xs text-gray-400 whitespace-nowrap">
                    {{ m.stok_before }} → {{ m.stok_after }}
                  </span>
                </div>
                <p v-if="m.catatan" class="text-xs text-gray-500 mt-0.5 truncate">{{ m.catatan }}</p>
                <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
                  <span>{{ m.user?.name || '—' }}</span>
                  <span>·</span>
                  <span>{{ new Date(m.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Movements pagination -->
          <div v-if="movementsTotalPgs > 1"
            class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-sm">
            <span class="text-gray-500 text-xs">Hal. {{ movementsPage }} / {{ movementsTotalPgs }}</span>
            <div class="flex gap-2">
              <button @click="openMovements(movementsDrug, movementsPage - 1)"
                :disabled="movementsPage === 1"
                class="btn-secondary px-3 py-1 text-xs">←</button>
              <button @click="openMovements(movementsDrug, movementsPage + 1)"
                :disabled="movementsPage === movementsTotalPgs"
                class="btn-secondary px-3 py-1 text-xs">→</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active  { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to        { opacity: 0; }
.slide-right-enter-active, .slide-right-leave-active { transition: all 0.25s ease; }
.slide-right-enter-from, .slide-right-leave-to       { opacity: 0; }
.expand-enter-active, .expand-leave-active { transition: all 0.2s ease; }
.expand-enter-from, .expand-leave-to       { opacity: 0; transform: translateY(-8px); }
</style>
