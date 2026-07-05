<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/utils/api'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { formatRupiah, formatDate } from '@/utils/format'
import { printInvoice } from '@/utils/invoice'

const cart = useCartStore()
const auth = useAuthStore()

function windowClose() {
  window.close()
}

// ─── Drug catalog ─────────────────────────────────────────────────────────────
const allDrugs      = ref([])
const loadingDrugs  = ref(true)
const searchQuery   = ref('')
const filterCat     = ref('')
const categories    = ref([])

const filteredDrugs = computed(() => {
  let list = allDrugs.value
  if (filterCat.value)
    list = list.filter(d => d.category_id == filterCat.value)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(d =>
      d.name.toLowerCase().includes(q) ||
      d.kode_obat.toLowerCase().includes(q)
    )
  }
  return list
})

async function loadCatalog() {
  loadingDrugs.value = true
  try {
    const [drugRes, catRes] = await Promise.all([
      api.get('/drugs', { params: { per_page: 200, is_active: true } }),
      api.get('/categories'),
    ])
    allDrugs.value   = drugRes.data.data ?? []
    categories.value = catRes.data.data  ?? catRes.data
  } catch (e) { console.error(e) }
  finally { loadingDrugs.value = false }
}

onMounted(loadCatalog)

// ─── Cart error message ───────────────────────────────────────────────────────
const errorMsg = ref('')

// ─── Item Configurator Modal ───────────────────────────────────────────────────
const showItemConfig  = ref(false)
const configDrug      = ref(null)
const configUnit      = ref(null)
const configQty       = ref(1)
const configDiskon    = ref(0)
const isEditing       = ref(false)
const originalUnitId  = ref(null)

function openItemConfig(drug, existingItem = null) {
  configDrug.value = drug
  if (existingItem) {
    configUnit.value   = existingItem.unit
    configQty.value    = existingItem.quantity
    configDiskon.value = existingItem.diskon || 0
    originalUnitId.value = existingItem.unit?.id ?? null
    isEditing.value    = true
  } else {
    configUnit.value   = drug.units && drug.units.length > 0 ? drug.units[0] : null
    configQty.value    = 1
    configDiskon.value = 0
    originalUnitId.value = null
    isEditing.value    = false
  }
  showItemConfig.value = true
}

function selectConfigUnit(unit) {
  configUnit.value = unit
  validateConfigQty()
}

function validateConfigQty() {
  if (configQty.value < 1 || !configQty.value) {
    configQty.value = 1
  }
  const maxStok = configDrug.value.stok
  const konversi = configUnit.value?.konversi ?? 1
  if (configQty.value * konversi > maxStok) {
    configQty.value = Math.max(1, Math.floor(maxStok / konversi))
  }
}

function incConfigQty() {
  const maxStok = configDrug.value.stok
  const konversi = configUnit.value?.konversi ?? 1
  if ((configQty.value + 1) * konversi <= maxStok) {
    configQty.value++
  }
}

function decConfigQty() {
  if (configQty.value > 1) {
    configQty.value--
  }
}

function saveItemConfig() {
  const drug = configDrug.value
  const unit = configUnit.value
  const qty = configQty.value
  const diskon = Number(configDiskon.value) || 0

  // Validate discount
  const harga = unit ? Number(unit.harga_jual) : Number(drug.harga_jual)
  if (diskon > harga * qty) {
    errorMsg.value = 'Diskon tidak boleh melebihi subtotal!'
    setTimeout(() => { errorMsg.value = '' }, 3000)
    return
  }

  // Validate stock
  const konversi = unit?.konversi ?? 1
  const maxStok = drug.stok
  if (qty * konversi > maxStok) {
    errorMsg.value = `Stok tidak mencukupi! Tersisa ${Math.floor(maxStok / konversi)} satuan.`
    setTimeout(() => { errorMsg.value = '' }, 3000)
    return
  }

  if (isEditing.value) {
    if (originalUnitId.value !== (unit?.id ?? null)) {
      cart.removeItem(drug.id, originalUnitId.value)
    }
    const existing = cart.items.find(i => i.drug.id === drug.id && (i.unit?.id ?? null) === (unit?.id ?? null))
    if (existing) {
      existing.quantity = qty
      existing.diskon = diskon
    } else {
      cart.addItem(drug, unit, qty)
      cart.updateDiskon(drug.id, unit?.id ?? null, diskon)
    }
  } else {
    const existing = cart.items.find(i => i.drug.id === drug.id && (i.unit?.id ?? null) === (unit?.id ?? null))
    if (existing) {
      existing.quantity += qty
      existing.diskon += diskon
    } else {
      cart.addItem(drug, unit, qty)
      cart.updateDiskon(drug.id, unit?.id ?? null, diskon)
    }
  }

  showItemConfig.value = false
  configDrug.value = null
  configUnit.value = null
  errorMsg.value = ''
}

function addToCart(drug) {
  openItemConfig(drug)
}

function incQty(item) {
  try { cart.updateQty(item.drug.id, item.unit?.id ?? null, item.quantity + 1) }
  catch (e) { errorMsg.value = e.message }
}
function decQty(item) { cart.updateQty(item.drug.id, item.unit?.id ?? null, item.quantity - 1) }

// ─── Checkout ─────────────────────────────────────────────────────────────────
const showCheckout    = ref(false)
const metode          = ref('tunai')
const bayar           = ref(0)
const diskonTotal     = ref(0)
const catatan         = ref('')
const loadingCheckout = ref(false)

const total      = computed(() => Math.max(cart.subtotal - diskonTotal.value, 0))
const kembalian  = computed(() => bayar.value - total.value)
const bayarValid = computed(() => bayar.value >= total.value)

function openCheckout() {
  bayar.value = total.value
  showCheckout.value = true
}

// ─── Invoice / receipt ────────────────────────────────────────────────────────
const receipt     = ref(null)
const showInvoice = ref(false)

async function processCheckout() {
  if (!bayarValid.value) return
  loadingCheckout.value = true
  errorMsg.value = ''
  try {
    const res = await api.post('/transactions', {
      bayar:        Number(bayar.value),
      diskon:       Number(diskonTotal.value),
      metode_bayar: metode.value,
      catatan:      catatan.value || null,
      items: cart.items.map(i => ({
        drug_id:      i.drug.id,
        drug_unit_id: i.unit?.id ?? null,
        quantity:     i.quantity,
        diskon:       i.diskon || 0,
      })),
    })
    receipt.value = res.data.transaction
    await loadCatalog()
    cart.clearCart()
    showCheckout.value = false
    bayar.value = 0; diskonTotal.value = 0; catatan.value = ''
    showInvoice.value = true
    txHistory.value.unshift(receipt.value)
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Transaksi gagal.'
  } finally {
    loadingCheckout.value = false
  }
}

function closeInvoice() { showInvoice.value = false; receipt.value = null }
function doPrint(tx)    { printInvoice(tx) }

// ─── Transaction history ──────────────────────────────────────────────────────
const showHistory     = ref(false)
const txHistory       = ref([])
const txLoading       = ref(false)
const txPage          = ref(1)
const txTotalPages    = ref(1)
const historyInvoice  = ref(null)

async function loadHistory(page = 1) {
  txLoading.value = true
  try {
    const res = await api.get('/transactions', {
      params: { per_page: 20, page, status: 'selesai' },
    })
    txHistory.value    = res.data.data
    txPage.value       = res.data.current_page
    txTotalPages.value = res.data.last_page
  } catch (e) { console.error(e) }
  finally { txLoading.value = false }
}

async function openHistory() {
  showHistory.value = true
  if (!txHistory.value.length) await loadHistory()
}

async function viewHistoryInvoice(tx) {
  try {
    const res = await api.get(`/transactions/${tx.id}`)
    historyInvoice.value = res.data
  } catch (_) {
    historyInvoice.value = tx
  }
}

function closeHistoryInvoice() { historyInvoice.value = null }

// ─── Helpers ──────────────────────────────────────────────────────────────────
function fmtDT(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
const metodeLabel = {
  tunai: 'Tunai', qris: 'QRIS',
  transfer: 'Transfer', kartu: 'Kartu',
}

// Helper: display label for cart item
function itemSatuan(item) {
  return item.unit?.satuan ?? item.drug.satuan ?? ''
}
function itemLabel(item) {
  return item.unit?.label ?? item.drug.satuan ?? ''
}
function itemHarga(item) {
  return item.unit ? Number(item.unit.harga_jual) : Number(item.drug.harga_jual)
}
</script>

<template>
  <div class="flex flex-col h-screen bg-gray-50 overflow-hidden">
    <!-- Standalone Header -->
    <header class="bg-primary-950 text-white px-6 py-3 flex items-center justify-between shadow-md flex-shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center text-white font-bold text-sm">
          A
        </div>
        <div>
          <h1 class="font-bold text-sm leading-none">Kasir / POS</h1>
          <p class="text-[10px] text-primary-300 mt-0.5">Apotek Algenz</p>
        </div>
      </div>

      <div class="flex items-center gap-4">
        <div class="text-right hidden sm:block">
          <p class="text-xs font-semibold leading-none text-white">{{ auth.user?.name }}</p>
          <p class="text-[10px] text-primary-400 capitalize leading-none mt-1">{{ auth.user?.role_display || auth.user?.role }}</p>
        </div>
        <button @click="windowClose"
          class="flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition-colors">
          ✕ Tutup Kasir
        </button>
      </div>
    </header>

    <!-- Main Content Area -->
    <div class="flex-1 p-4 overflow-hidden">
      <div class="flex gap-4 h-full">

    <!-- ── LEFT: Drug Catalog ─────────────────────────────────────────────── -->
    <div class="flex-1 flex flex-col min-w-0">

      <!-- Toolbar -->
      <div class="card p-3 mb-3 flex flex-wrap gap-2 items-center">
        <div class="relative flex-1 min-w-40">
          <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">🔍</span>
          <input v-model="searchQuery" type="text"
            placeholder="Cari nama / kode obat..."
            class="form-input pl-9 w-full text-sm" />
        </div>
        <select v-model="filterCat" class="form-select w-40 text-sm">
          <option value="">Semua Kategori</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <button @click="openHistory"
          class="flex items-center gap-1.5 px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm text-gray-700 font-medium transition-colors">
          🕓 Riwayat
        </button>
      </div>

      <!-- Error banner -->
      <Transition name="fade">
        <div v-if="errorMsg"
          class="mb-2 px-4 py-2 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          ⚠️ {{ errorMsg }}
        </div>
      </Transition>

      <!-- Drug grid -->
      <div class="flex-1 overflow-y-auto">
        <!-- Loading skeleton -->
        <div v-if="loadingDrugs" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
          <div v-for="i in 12" :key="i" class="card p-3 animate-pulse h-24">
            <div class="h-3 bg-gray-200 rounded mb-2 w-3/4"></div>
            <div class="h-3 bg-gray-200 rounded mb-3 w-1/2"></div>
            <div class="h-5 bg-gray-200 rounded"></div>
          </div>
        </div>

        <!-- Empty -->
        <div v-else-if="filteredDrugs.length === 0"
          class="flex flex-col items-center justify-center py-20 text-gray-400">
          <span class="text-5xl mb-3">💊</span>
          <p class="text-sm">Tidak ada obat ditemukan.</p>
        </div>

        <!-- Grid -->
        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
          <button
            v-for="drug in filteredDrugs" :key="drug.id"
            @click="addToCart(drug)"
            :disabled="drug.stok === 0"
            class="card p-3 text-left hover:border-primary-400 hover:shadow transition-all duration-100 active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed group"
          >
            <div class="flex items-start justify-between mb-1">
              <span class="text-[10px] font-mono text-gray-400 truncate flex-1">{{ drug.kode_obat }}</span>
              <span class="text-[10px] font-semibold px-1 py-0.5 rounded ml-1 flex-shrink-0"
                :class="drug.stok === 0 ? 'bg-red-100 text-red-600'
                       : drug.stok <= drug.stok_minimum ? 'bg-amber-100 text-amber-700'
                       : 'bg-green-100 text-green-700'">
                {{ drug.stok }}
              </span>
            </div>
            <p class="text-xs font-semibold text-gray-800 group-hover:text-primary-700 leading-snug mb-1 line-clamp-2">
              {{ drug.name }}
            </p>
            <!-- Unit badges or single price -->
            <div v-if="drug.units && drug.units.length > 0" class="mt-auto space-y-0.5">
              <div v-for="u in drug.units.slice(0, 2)" :key="u.id"
                class="flex items-center justify-between text-[10px]">
                <span class="text-gray-500 font-medium">{{ u.label }}</span>
                <span class="font-bold text-primary-700">{{ formatRupiah(u.harga_jual) }}</span>
              </div>
              <p v-if="drug.units.length > 2" class="text-[9px] text-primary-500 font-medium">
                +{{ drug.units.length - 2 }} satuan lainnya…
              </p>
            </div>
            <p v-else class="text-[11px] font-bold text-primary-700 mt-auto">{{ formatRupiah(drug.harga_jual) }}</p>
          </button>
        </div>
      </div>
    </div>

    <!-- ── RIGHT: Cart ────────────────────────────────────────────────────── -->
    <div class="w-72 flex flex-col card flex-shrink-0">
      <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 text-sm">🛒 Keranjang</h3>
        <span class="badge-blue text-xs">{{ cart.totalItems }}</span>
      </div>

      <div class="flex-1 overflow-y-auto p-2 space-y-1.5">
        <div v-if="cart.items.length === 0"
          class="flex flex-col items-center justify-center h-full text-gray-400 py-8">
          <span class="text-3xl mb-2">🛒</span>
          <p class="text-xs">Keranjang kosong</p>
        </div>
        <div v-for="item in cart.items" :key="`${item.drug.id}-${item.unit?.id ?? 'def'}`"
          @click="openItemConfig(item.drug, item)"
          class="bg-gray-50 rounded-lg p-2.5 border border-gray-100 cursor-pointer hover:border-primary-300 transition-colors group/item">
          <div class="flex items-start justify-between mb-1">
            <div class="flex-1 pr-1">
              <p class="text-xs font-semibold text-gray-800 leading-tight group-hover/item:text-primary-700 transition-colors">{{ item.drug.name }}</p>
              <span class="text-[10px] font-medium text-primary-600 bg-primary-50 px-1.5 py-0.5 rounded-full mt-0.5 inline-block">
                {{ itemLabel(item) }}
              </span>
            </div>
            <button @click.stop="cart.removeItem(item.drug.id, item.unit?.id ?? null)"
              class="text-red-400 hover:text-red-600 text-xs leading-none flex-shrink-0 mt-0.5">✕</button>
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1" @click.stop>
              <button @click="decQty(item)"
                class="w-5 h-5 rounded bg-gray-200 hover:bg-gray-300 text-xs font-bold flex items-center justify-center">−</button>
              <span class="text-xs font-bold w-6 text-center">{{ item.quantity }}</span>
              <button @click="incQty(item)"
                class="w-5 h-5 rounded bg-gray-200 hover:bg-gray-300 text-xs font-bold flex items-center justify-center">+</button>
            </div>
            <div class="text-right">
              <p v-if="item.diskon > 0" class="text-[9px] text-red-500 font-medium line-through">
                {{ formatRupiah(itemHarga(item) * item.quantity) }}
              </p>
              <p class="text-xs font-bold text-primary-700">
                {{ formatRupiah(itemHarga(item) * item.quantity - (item.diskon || 0)) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-100 p-3 space-y-2">
        <div class="flex justify-between text-sm">
          <span class="text-gray-600">Subtotal</span>
          <span class="font-semibold">{{ formatRupiah(cart.subtotal) }}</span>
        </div>
        <button @click="openCheckout" :disabled="cart.items.length === 0"
          class="btn-primary w-full justify-center py-2.5 text-sm">
          💳 Proses Pembayaran
        </button>
        <button @click="cart.clearCart()" :disabled="cart.items.length === 0"
          class="btn-secondary w-full justify-center text-xs py-1.5">
          🗑️ Kosongkan
        </button>
      </div>
    </div>

    <!-- ── Item Configurator Modal ─────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showItemConfig && configDrug"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showItemConfig = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
          
          <!-- Header with gradient -->
          <div class="bg-gradient-to-r from-primary-700 to-primary-800 px-5 py-4 flex items-center justify-between">
            <div>
              <h3 class="text-white font-semibold text-base">
                {{ isEditing ? 'Edit Item Keranjang' : 'Tambah Obat' }}
              </h3>
              <p class="text-primary-100 text-xs mt-0.5">{{ configDrug.name }} ({{ configDrug.kode_obat }})</p>
            </div>
            <button @click="showItemConfig = false" class="text-primary-200 hover:text-white text-xl">✕</button>
          </div>

          <!-- Body -->
          <div class="p-5 space-y-4">
            
            <!-- Stock Information Badge -->
            <div class="flex justify-between items-center bg-gray-50 px-3 py-2 rounded-lg text-xs">
              <span class="text-gray-500">Stok Tersedia:</span>
              <span class="font-bold text-gray-800">
                {{ configDrug.stok }} {{ configDrug.satuan }}
              </span>
            </div>

            <!-- Unit Selector -->
            <div>
              <label class="form-label text-xs font-semibold text-gray-600 mb-1.5 block">Pilih Satuan</label>
              <div class="grid grid-cols-1 gap-2">
                <!-- Unit list -->
                <button
                  v-for="u in configDrug.units"
                  :key="u.id"
                  @click="selectConfigUnit(u)"
                  type="button"
                  class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl border-2 transition-all text-left"
                  :class="configUnit?.id === u.id 
                    ? 'border-primary-500 bg-primary-50/50 text-primary-900 font-medium' 
                    : 'border-gray-100 hover:border-gray-300 text-gray-700'"
                >
                  <div class="flex-1 min-w-0 pr-2">
                    <p class="text-xs font-semibold">{{ u.label }}</p>
                    <p class="text-[10px] text-gray-400">
                      {{ u.satuan }} <span v-if="u.konversi > 1"> · isi {{ u.konversi }} {{ configDrug.satuan }}</span>
                    </p>
                  </div>
                  <p class="font-bold text-primary-700 text-xs whitespace-nowrap">{{ formatRupiah(u.harga_jual) }}</p>
                </button>

                <!-- Default Price fallback -->
                <button
                  @click="selectConfigUnit(null)"
                  type="button"
                  class="flex items-center justify-between px-3 py-2.5 rounded-xl border-2 transition-all text-left"
                  :class="configUnit === null 
                    ? 'border-primary-500 bg-primary-50/50 text-primary-900 font-medium' 
                    : 'border-gray-100 hover:border-gray-300 text-gray-700'"
                >
                  <div class="flex-1 min-w-0 pr-2">
                    <p class="text-xs font-semibold">Harga Default</p>
                    <p class="text-[10px] text-gray-400">{{ configDrug.satuan }} (Satuan Dasar)</p>
                  </div>
                  <p class="font-bold text-primary-700 text-xs whitespace-nowrap">{{ formatRupiah(configDrug.harga_jual) }}</p>
                </button>
              </div>
            </div>

            <!-- Quantity & Discount Grid -->
            <div class="grid grid-cols-2 gap-4">
              <!-- Quantity selector -->
              <div>
                <label class="form-label text-xs font-semibold text-gray-600 mb-1.5 block">Jumlah (Qty)</label>
                <div class="flex items-center justify-between border-2 border-gray-100 rounded-xl px-2 py-1 bg-white">
                  <button @click="decConfigQty" type="button"
                    class="w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 active:scale-95 text-gray-600 font-bold flex items-center justify-center transition-all">−</button>
                  <input v-model.number="configQty" type="number" min="1" @input="validateConfigQty"
                    class="w-12 text-center font-bold text-sm border-0 focus:ring-0 p-0 text-gray-800" />
                  <button @click="incConfigQty" type="button"
                    class="w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 active:scale-95 text-gray-600 font-bold flex items-center justify-center transition-all">+</button>
                </div>
              </div>

              <!-- Discount input -->
              <div>
                <label class="form-label text-xs font-semibold text-gray-600 mb-1.5 block">Diskon Item (Rp)</label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-[10px] font-bold text-gray-400">Rp</span>
                  <input v-model.number="configDiskon" type="number" min="0"
                    class="form-input pl-8 w-full text-xs font-semibold" placeholder="0" />
                </div>
              </div>
            </div>

            <!-- Subtotal Card -->
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-3.5 flex justify-between items-center">
              <div>
                <p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Subtotal Item</p>
                <p class="text-[10px] text-gray-400 mt-0.5">
                  {{ configQty }} × {{ formatRupiah(configUnit ? configUnit.harga_jual : configDrug.harga_jual) }}
                  <span v-if="configDiskon > 0" class="text-red-500"> - {{ formatRupiah(configDiskon) }}</span>
                </p>
              </div>
              <p class="text-base font-extrabold text-primary-700">
                {{ formatRupiah(Math.max((configUnit ? configUnit.harga_jual : configDrug.harga_jual) * configQty - configDiskon, 0)) }}
              </p>
            </div>

            <!-- Footer Action Buttons -->
            <div class="flex gap-3 pt-2">
              <button @click="showItemConfig = false" type="button" class="btn-secondary flex-1 justify-center text-xs py-2">
                Batal
              </button>
              <button @click="saveItemConfig" type="button" class="btn-primary flex-1 justify-center text-xs py-2">
                {{ isEditing ? 'Simpan' : 'Tambah ke Keranjang' }}
              </button>
            </div>

          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Checkout Modal ─────────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showCheckout"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showCheckout = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
          <div class="bg-primary-700 px-6 py-4 flex items-center justify-between">
            <h3 class="text-white font-semibold text-lg">💳 Pembayaran</h3>
            <button @click="showCheckout = false" class="text-primary-200 hover:text-white text-xl">✕</button>
          </div>
          <div class="p-6 space-y-4">
            <!-- Cart summary -->
            <div class="bg-gray-50 rounded-xl p-3 space-y-1 text-xs max-h-36 overflow-y-auto">
              <div v-for="item in cart.items" :key="`s-${item.drug.id}-${item.unit?.id}`"
                class="flex justify-between text-gray-600">
                <span class="truncate flex-1 mr-2">
                  {{ item.drug.name }}
                  <span class="text-primary-600 font-medium">({{ itemLabel(item) }})</span>
                  × {{ item.quantity }}
                </span>
                <span class="font-semibold text-gray-800 whitespace-nowrap">{{ formatRupiah(itemHarga(item) * item.quantity) }}</span>
              </div>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Subtotal</span>
                <span>{{ formatRupiah(cart.subtotal) }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Diskon (Rp)</span>
                <input v-model.number="diskonTotal" type="number" min="0"
                  class="form-input w-32 text-right text-sm py-1" />
              </div>
              <div class="flex justify-between font-bold text-base border-t pt-2">
                <span>Total</span>
                <span class="text-primary-700">{{ formatRupiah(total) }}</span>
              </div>
            </div>

            <div>
              <label class="form-label">Metode Pembayaran</label>
              <select v-model="metode" class="form-select">
                <option value="tunai">Tunai</option>
                <option value="qris">QRIS</option>
                <option value="transfer">Transfer Bank</option>
                <option value="kartu">Kartu Debit/Kredit</option>
              </select>
            </div>

            <div>
              <label class="form-label">Jumlah Bayar</label>
              <input v-model.number="bayar" type="number" :min="total"
                class="form-input text-lg font-bold" />
            </div>

            <div v-if="metode === 'tunai'"
              class="flex justify-between font-semibold text-sm p-3 rounded-lg"
              :class="kembalian >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
              <span>Kembalian</span>
              <span>{{ formatRupiah(Math.max(kembalian, 0)) }}</span>
            </div>

            <div>
              <label class="form-label">Catatan (opsional)</label>
              <input v-model="catatan" type="text" maxlength="200" class="form-input"
                placeholder="Catatan transaksi..." />
            </div>

            <div v-if="errorMsg" class="text-sm text-red-600 bg-red-50 p-3 rounded-lg">{{ errorMsg }}</div>

            <div class="flex gap-3 pt-2">
              <button @click="showCheckout = false" class="btn-secondary flex-1 justify-center">Batal</button>
              <button @click="processCheckout" :disabled="!bayarValid || loadingCheckout"
                class="btn-primary flex-1 justify-center">
                <svg v-if="loadingCheckout" class="animate-spin w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                {{ loadingCheckout ? 'Memproses...' : '✓ Bayar' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Invoice Modal (after successful checkout) ─────────────────────── -->
    <Transition name="modal">
      <div v-if="showInvoice && receipt"
        class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
        @click.self="closeInvoice">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm flex flex-col max-h-[90vh]">

          <div class="bg-green-600 px-6 py-4 rounded-t-2xl flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
              <span class="text-xl">✅</span>
              <div>
                <p class="text-white font-bold">Transaksi Berhasil!</p>
                <p class="text-green-100 text-xs">{{ receipt.nomor_transaksi }}</p>
              </div>
            </div>
            <button @click="closeInvoice" class="text-green-200 hover:text-white text-xl">✕</button>
          </div>

          <!-- Preview -->
          <div class="flex-1 overflow-y-auto bg-gray-50 p-4">
            <div class="bg-white rounded-xl border border-gray-100 p-4 font-mono text-xs leading-relaxed">
              <!-- Header -->
              <div class="text-center mb-3">
                <div class="font-bold text-sm">💊 Apotek Algenz</div>
                <div class="text-gray-500 text-[10px]">Jl. Kesehatan No. 1 · Telp. (0xxx) xxx-xxxx</div>
              </div>
              <div class="border-t border-dashed border-gray-400 my-2"></div>
              <!-- Meta -->
              <div class="space-y-0.5 mb-2">
                <div class="flex justify-between"><span>No. Transaksi</span><span class="font-bold">{{ receipt.nomor_transaksi }}</span></div>
                <div class="flex justify-between"><span>Tanggal</span><span>{{ fmtDT(receipt.created_at) }}</span></div>
                <div class="flex justify-between"><span>Kasir</span><span>{{ receipt.user?.name ?? '-' }}</span></div>
              </div>
              <div class="border-t border-dashed border-gray-400 my-2"></div>
              <!-- Items -->
              <div class="space-y-1 mb-2">
                <div class="grid grid-cols-[1fr_auto_auto] gap-1 font-bold text-[10px] border-b border-gray-300 pb-1">
                  <span>Nama Obat</span><span class="text-right">Qty×Harga</span><span class="text-right">Sub</span>
                </div>
                <div v-for="item in receipt.items" :key="item.id"
                  class="grid grid-cols-[1fr_auto_auto] gap-1 text-[10px] border-b border-dotted border-gray-200 pb-0.5">
                  <span class="break-words">
                    {{ item.drug_name }}
                    <span v-if="item.satuan" class="text-gray-400">({{ item.satuan }})</span>
                  </span>
                  <span class="text-right whitespace-nowrap">{{ item.quantity }}×{{ formatRupiah(item.harga_jual) }}</span>
                  <span class="text-right font-semibold whitespace-nowrap">{{ formatRupiah(item.subtotal) }}</span>
                </div>
              </div>
              <div class="border-t border-dashed border-gray-400 my-2"></div>
              <!-- Totals -->
              <div class="space-y-0.5">
                <div class="flex justify-between"><span>Subtotal</span><span>{{ formatRupiah(receipt.subtotal) }}</span></div>
                <div v-if="Number(receipt.diskon) > 0" class="flex justify-between text-red-600">
                  <span>Diskon</span><span>- {{ formatRupiah(receipt.diskon) }}</span>
                </div>
                <div class="flex justify-between font-bold text-sm border-t border-gray-300 pt-1 mt-1">
                  <span>TOTAL</span><span>{{ formatRupiah(receipt.total) }}</span>
                </div>
                <div class="flex justify-between"><span>Metode</span><span>{{ metodeLabel[receipt.metode_bayar] ?? receipt.metode_bayar }}</span></div>
                <div class="flex justify-between"><span>Dibayar</span><span>{{ formatRupiah(receipt.bayar) }}</span></div>
                <div class="flex justify-between font-semibold text-green-700">
                  <span>Kembalian</span><span>{{ formatRupiah(receipt.kembalian) }}</span>
                </div>
              </div>
              <div class="border-t border-dashed border-gray-400 my-2"></div>
              <div class="text-center text-[10px] text-gray-500">
                <p>Terima kasih atas kepercayaan Anda.</p>
                <p>Simpan struk ini sebagai bukti pembelian.</p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="p-4 border-t border-gray-100 flex gap-2 flex-shrink-0">
            <button @click="closeInvoice" class="btn-secondary flex-1 justify-center text-sm">Transaksi Baru</button>
            <button @click="doPrint(receipt)"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-primary-700 hover:bg-primary-800 text-white text-sm font-semibold rounded-lg transition-colors">
              🖨️ Cetak Invoice
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Transaction History Drawer ────────────────────────────────────── -->
    <Transition name="slide-right">
      <div v-if="showHistory" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/40" @click="showHistory = false"></div>
        <div class="relative w-full max-w-lg bg-white h-full flex flex-col shadow-2xl">

          <div class="bg-gray-800 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div>
              <h3 class="text-white font-semibold">🕓 Riwayat Transaksi</h3>
              <p class="text-gray-400 text-xs mt-0.5">Transaksi selesai hari ini</p>
            </div>
            <button @click="showHistory = false" class="text-gray-400 hover:text-white text-xl">✕</button>
          </div>

          <div class="flex-1 overflow-y-auto">
            <!-- Loading -->
            <div v-if="txLoading" class="p-4 space-y-3">
              <div v-for="i in 6" :key="i" class="h-16 bg-gray-100 animate-pulse rounded-xl"></div>
            </div>
            <!-- Empty -->
            <div v-else-if="txHistory.length === 0"
              class="flex flex-col items-center justify-center py-20 text-gray-400">
              <span class="text-4xl mb-2">📭</span>
              <p class="text-sm">Belum ada transaksi.</p>
            </div>
            <!-- List -->
            <div v-else class="divide-y divide-gray-100">
              <div v-for="tx in txHistory" :key="tx.id"
                class="px-5 py-3.5 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                      <span class="font-mono text-xs font-semibold text-gray-800">{{ tx.nomor_transaksi }}</span>
                      <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                        :class="tx.status === 'selesai' ? 'bg-green-100 text-green-700'
                               : tx.status === 'dibatalkan' ? 'bg-red-100 text-red-600'
                               : 'bg-gray-100 text-gray-600'">
                        {{ tx.status }}
                      </span>
                    </div>
                    <p class="text-xs text-gray-500">{{ fmtDT(tx.created_at) }} · {{ tx.user?.name ?? '-' }}</p>
                    <p class="text-sm font-bold text-primary-700 mt-0.5">{{ formatRupiah(tx.total) }}</p>
                    <p class="text-[10px] text-gray-400 capitalize">{{ metodeLabel[tx.metode_bayar] ?? tx.metode_bayar }}</p>
                  </div>
                  <div class="flex flex-col gap-1.5 flex-shrink-0">
                    <button @click="viewHistoryInvoice(tx)"
                      class="px-2.5 py-1 text-xs bg-primary-50 text-primary-700 hover:bg-primary-100 rounded-lg font-medium transition-colors">
                      👁 Invoice
                    </button>
                    <button @click="async () => { await viewHistoryInvoice(tx); doPrint(historyInvoice) }"
                      class="px-2.5 py-1 text-xs bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg font-medium transition-colors">
                      🖨️ Cetak
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="txTotalPages > 1"
            class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-sm flex-shrink-0">
            <span class="text-xs text-gray-500">Hal. {{ txPage }} / {{ txTotalPages }}</span>
            <div class="flex gap-2">
              <button @click="loadHistory(txPage - 1)" :disabled="txPage === 1"
                class="btn-secondary px-3 py-1 text-xs">←</button>
              <button @click="loadHistory(txPage + 1)" :disabled="txPage === txTotalPages"
                class="btn-secondary px-3 py-1 text-xs">→</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── History Invoice Viewer ─────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="historyInvoice"
        class="fixed inset-0 bg-black/60 flex items-center justify-center z-[60] p-4"
        @click.self="closeHistoryInvoice">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm flex flex-col max-h-[90vh]">

          <div class="bg-gray-800 px-6 py-4 rounded-t-2xl flex items-center justify-between flex-shrink-0">
            <div>
              <p class="text-white font-semibold text-sm">Invoice</p>
              <p class="text-gray-300 text-xs">{{ historyInvoice.nomor_transaksi }}</p>
            </div>
            <button @click="closeHistoryInvoice" class="text-gray-400 hover:text-white text-xl">✕</button>
          </div>

          <div class="flex-1 overflow-y-auto bg-gray-50 p-4">
            <div class="bg-white rounded-xl border border-gray-100 p-4 font-mono text-xs leading-relaxed">
              <div class="text-center mb-3">
                <div class="font-bold text-sm">💊 Apotek Algenz</div>
                <div class="text-gray-500 text-[10px]">Jl. Kesehatan No. 1 · Telp. (0xxx) xxx-xxxx</div>
              </div>
              <div class="border-t border-dashed border-gray-400 my-2"></div>
              <div class="space-y-0.5 mb-2">
                <div class="flex justify-between"><span>No. Transaksi</span><span class="font-bold">{{ historyInvoice.nomor_transaksi }}</span></div>
                <div class="flex justify-between"><span>Tanggal</span><span>{{ fmtDT(historyInvoice.created_at) }}</span></div>
                <div class="flex justify-between"><span>Kasir</span><span>{{ historyInvoice.user?.name ?? '-' }}</span></div>
              </div>
              <div class="border-t border-dashed border-gray-400 my-2"></div>
              <div class="space-y-1 mb-2">
                <div class="grid grid-cols-[1fr_auto_auto] gap-1 font-bold text-[10px] border-b border-gray-300 pb-1">
                  <span>Nama Obat</span><span class="text-right">Qty×Harga</span><span class="text-right">Sub</span>
                </div>
                <div v-for="item in historyInvoice.items" :key="item.id"
                  class="grid grid-cols-[1fr_auto_auto] gap-1 text-[10px] border-b border-dotted border-gray-200 pb-0.5">
                  <span class="break-words">
                    {{ item.drug_name }}
                    <span v-if="item.satuan" class="text-gray-400">({{ item.satuan }})</span>
                  </span>
                  <span class="text-right whitespace-nowrap">{{ item.quantity }}×{{ formatRupiah(item.harga_jual) }}</span>
                  <span class="text-right font-semibold whitespace-nowrap">{{ formatRupiah(item.subtotal) }}</span>
                </div>
              </div>
              <div class="border-t border-dashed border-gray-400 my-2"></div>
              <div class="space-y-0.5">
                <div class="flex justify-between"><span>Subtotal</span><span>{{ formatRupiah(historyInvoice.subtotal) }}</span></div>
                <div v-if="Number(historyInvoice.diskon) > 0" class="flex justify-between text-red-600">
                  <span>Diskon</span><span>- {{ formatRupiah(historyInvoice.diskon) }}</span>
                </div>
                <div class="flex justify-between font-bold text-sm border-t border-gray-300 pt-1 mt-1">
                  <span>TOTAL</span><span>{{ formatRupiah(historyInvoice.total) }}</span>
                </div>
                <div class="flex justify-between"><span>Metode</span><span>{{ metodeLabel[historyInvoice.metode_bayar] ?? historyInvoice.metode_bayar }}</span></div>
                <div class="flex justify-between"><span>Dibayar</span><span>{{ formatRupiah(historyInvoice.bayar) }}</span></div>
                <div class="flex justify-between font-semibold text-green-700">
                  <span>Kembalian</span><span>{{ formatRupiah(historyInvoice.kembalian) }}</span>
                </div>
              </div>
              <div class="border-t border-dashed border-gray-400 my-2"></div>
              <div class="text-center text-[10px] text-gray-500">
                <p>Terima kasih atas kepercayaan Anda.</p>
                <p>Simpan struk ini sebagai bukti pembelian.</p>
              </div>
            </div>
          </div>

          <div class="p-4 border-t border-gray-100 flex gap-2 flex-shrink-0">
            <button @click="closeHistoryInvoice" class="btn-secondary flex-1 justify-center text-sm">Tutup</button>
            <button @click="doPrint(historyInvoice)"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-primary-700 hover:bg-primary-800 text-white text-sm font-semibold rounded-lg transition-colors">
              🖨️ Cetak Invoice
            </button>
          </div>
        </div>
      </div>
    </Transition>

    </div>
  </div>
</div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active  { transition: all 0.2s ease; }
.modal-enter-from, .modal-leave-to        { opacity: 0; }
.fade-enter-active, .fade-leave-active    { transition: all 0.2s; }
.fade-enter-from, .fade-leave-to          { opacity: 0; }
.slide-right-enter-active, .slide-right-leave-active { transition: all 0.25s ease; }
.slide-right-enter-from, .slide-right-leave-to       { opacity: 0; }
.line-clamp-2 {
  display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden;
}
</style>
