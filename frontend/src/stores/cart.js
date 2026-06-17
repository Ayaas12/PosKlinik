import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCartStore = defineStore('cart', () => {
  const items = ref([]) // { drug, quantity, diskon }

  const subtotal = computed(() =>
    items.value.reduce((sum, item) => {
      return sum + (item.drug.harga_jual * item.quantity) - (item.diskon || 0)
    }, 0)
  )

  const totalItems = computed(() => items.value.reduce((s, i) => s + i.quantity, 0))

  function addItem(drug, qty = 1) {
    const existing = items.value.find(i => i.drug.id === drug.id)
    if (existing) {
      const newQty = existing.quantity + qty
      if (newQty > drug.stok) {
        throw new Error(`Stok ${drug.name} hanya tersisa ${drug.stok}.`)
      }
      existing.quantity = newQty
    } else {
      if (qty > drug.stok) {
        throw new Error(`Stok ${drug.name} hanya tersisa ${drug.stok}.`)
      }
      items.value.push({ drug, quantity: qty, diskon: 0 })
    }
  }

  function removeItem(drugId) {
    items.value = items.value.filter(i => i.drug.id !== drugId)
  }

  function updateQty(drugId, qty) {
    const item = items.value.find(i => i.drug.id === drugId)
    if (!item) return
    if (qty <= 0) { removeItem(drugId); return }
    if (qty > item.drug.stok) {
      throw new Error(`Stok ${item.drug.name} hanya tersisa ${item.drug.stok}.`)
    }
    item.quantity = qty
  }

  function updateDiskon(drugId, diskon) {
    const item = items.value.find(i => i.drug.id === drugId)
    if (item) item.diskon = diskon
  }

  function clearCart() {
    items.value = []
  }

  return { items, subtotal, totalItems, addItem, removeItem, updateQty, updateDiskon, clearCart }
})
