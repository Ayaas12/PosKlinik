import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCartStore = defineStore('cart', () => {
  // Each item: { drug, unit, quantity, diskon }
  // unit = DrugUnit object or null (null = use drug.harga_jual default)
  const items = ref([])

  // Unique cart key: drug+unit combination so same drug with different units = separate rows
  function itemKey(drugId, unitId) {
    return `${drugId}-${unitId ?? 'default'}`
  }

  const subtotal = computed(() =>
    items.value.reduce((sum, item) => {
      const harga = item.unit ? Number(item.unit.harga_jual) : Number(item.drug.harga_jual)
      return sum + (harga * item.quantity) - (item.diskon || 0)
    }, 0)
  )

  const totalItems = computed(() => items.value.reduce((s, i) => s + i.quantity, 0))

  /**
   * Add a drug (with optional unit) to the cart.
   * @param {Object} drug   - Drug object (from catalog)
   * @param {Object|null} unit   - DrugUnit object or null for drug default price
   * @param {number} qty    - quantity to add
   */
  function addItem(drug, unit = null, qty = 1) {
    const key = itemKey(drug.id, unit?.id)
    const existing = items.value.find(i => itemKey(i.drug.id, i.unit?.id) === key)
    const maxStok = drug.stok
    // Stock is in base units; each sold unit consumes (unit.konversi ?? 1) base units
    const konversi = unit?.konversi ?? 1

    if (existing) {
      const newQty = existing.quantity + qty
      if (newQty * konversi > maxStok) {
        throw new Error(`Stok ${drug.name} tidak mencukupi. Tersisa ${Math.floor(maxStok / konversi)} ${unit?.satuan ?? drug.satuan}.`)
      }
      existing.quantity = newQty
    } else {
      if (qty * konversi > maxStok) {
        throw new Error(`Stok ${drug.name} tidak mencukupi. Tersisa ${Math.floor(maxStok / konversi)} ${unit?.satuan ?? drug.satuan}.`)
      }
      items.value.push({ drug, unit, quantity: qty, diskon: 0 })
    }
  }

  function removeItem(drugId, unitId = null) {
    const key = itemKey(drugId, unitId)
    items.value = items.value.filter(i => itemKey(i.drug.id, i.unit?.id) !== key)
  }

  function updateQty(drugId, unitId, qty) {
    const key = itemKey(drugId, unitId)
    const item = items.value.find(i => itemKey(i.drug.id, i.unit?.id) === key)
    if (!item) return
    if (qty <= 0) { removeItem(drugId, unitId); return }
    const konversi = item.unit?.konversi ?? 1
    if (qty * konversi > item.drug.stok) {
      throw new Error(`Stok ${item.drug.name} tidak mencukupi. Tersisa ${Math.floor(item.drug.stok / konversi)} ${item.unit?.satuan ?? item.drug.satuan}.`)
    }
    item.quantity = qty
  }

  function updateDiskon(drugId, unitId, diskon) {
    const key = itemKey(drugId, unitId)
    const item = items.value.find(i => itemKey(i.drug.id, i.unit?.id) === key)
    if (item) item.diskon = diskon
  }

  function clearCart() {
    items.value = []
  }

  return { items, subtotal, totalItems, addItem, removeItem, updateQty, updateDiskon, clearCart }
})
