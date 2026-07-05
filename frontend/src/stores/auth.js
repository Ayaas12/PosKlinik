import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/utils/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('pos_user') || 'null'))
  const loading = ref(false)

  const isLoggedIn = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isApoteker = computed(() => user.value?.role === 'apoteker')
  const isKasir = computed(() => user.value?.role === 'kasir')
  const canManageInventory = computed(() =>
    user.value?.role === 'admin' || user.value?.role === 'apoteker'
  )

  async function login(email, password) {
    loading.value = true
    try {
      const response = await api.post('/login', { email, password })
      const { token, user: userData } = response.data
      // Store token for use in every subsequent request
      localStorage.setItem('pos_token', token)
      user.value = userData
      localStorage.setItem('pos_user', JSON.stringify(userData))
      return { success: true }
    } catch (error) {
      const message = error.response?.data?.message
        || error.response?.data?.errors?.email?.[0]
        || 'Login gagal. Periksa email dan password.'
      return { success: false, message }
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch (_) { /* ignore */ }
    user.value = null
    localStorage.removeItem('pos_user')
    localStorage.removeItem('pos_token')
  }

  async function fetchMe() {
    try {
      const res = await api.get('/me')
      user.value = res.data.user
      localStorage.setItem('pos_user', JSON.stringify(res.data.user))
    } catch (_) {
      user.value = null
      localStorage.removeItem('pos_user')
      localStorage.removeItem('pos_token')
    }
  }

  return { user, loading, isLoggedIn, isAdmin, isApoteker, isKasir, canManageInventory, login, logout, fetchMe }
})
