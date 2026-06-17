<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits(['toggle-sidebar'])
const auth = useAuthStore()
const router = useRouter()
const loggingOut = ref(false)
const dropdownOpen = ref(false)

async function handleLogout() {
  loggingOut.value = true
  await auth.logout()
  router.push('/login')
}

// Page title map
import { useRoute } from 'vue-router'
const route = useRoute()
const pageTitles = {
  '/dashboard': 'Dashboard',
  '/kasir': 'Kasir / Transaksi Penjualan',
  '/stok': 'Manajemen Stok Obat',
  '/laporan': 'Laporan & Analitik',
  '/pengguna': 'Manajemen Pengguna',
}
</script>

<template>
  <header class="sticky top-0 z-20 bg-white border-b border-gray-200 shadow-sm">
    <div class="flex items-center justify-between px-6 h-14">
      <!-- Left: toggle + title -->
      <div class="flex items-center gap-4">
        <button
          @click="emit('toggle-sidebar')"
          class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
          title="Toggle sidebar"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-sm font-semibold text-gray-800">
          {{ pageTitles[route.path] || 'POS Apotek' }}
        </h1>
      </div>

      <!-- Right: user menu -->
      <div class="relative">
        <button
          @click="dropdownOpen = !dropdownOpen"
          class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors"
        >
          <div class="w-7 h-7 bg-primary-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
            {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div class="text-left hidden sm:block">
            <p class="text-xs font-semibold text-gray-800 leading-none">{{ auth.user?.name }}</p>
            <p class="text-xs text-gray-500 capitalize leading-none mt-0.5">{{ auth.user?.role_display || auth.user?.role }}</p>
          </div>
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <!-- Dropdown -->
        <Transition name="dropdown">
          <div v-if="dropdownOpen" class="absolute right-0 mt-1 w-44 bg-white rounded-xl border border-gray-200 shadow-lg py-1 z-50">
            <button
              @click="handleLogout"
              :disabled="loggingOut"
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              {{ loggingOut ? 'Keluar...' : 'Keluar' }}
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>

  <!-- Overlay to close dropdown -->
  <div v-if="dropdownOpen" class="fixed inset-0 z-10" @click="dropdownOpen = false" />
</template>

<style scoped>
.dropdown-enter-active, .dropdown-leave-active { transition: all 0.15s ease; }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
