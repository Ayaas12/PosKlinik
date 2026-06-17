<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

defineProps({ open: Boolean })
defineEmits(['toggle'])

const auth = useAuthStore()
const route = useRoute()

const navItems = computed(() => {
  const all = [
    { to: '/dashboard', icon: '📊', label: 'Dashboard', roles: ['admin', 'apoteker', 'kasir'] },
    { to: '/kasir', icon: '🛒', label: 'Kasir / POS', roles: ['admin', 'apoteker', 'kasir'] },
    { to: '/stok',     icon: '💊', label: 'Manajemen Stok',  roles: ['admin', 'apoteker'] },
    { to: '/batch',    icon: '📦', label: 'Manajemen Batch', roles: ['admin', 'apoteker'] },
    { to: '/laporan', icon: '📈', label: 'Laporan', roles: ['admin', 'apoteker'] },
    { to: '/pengguna', icon: '👥', label: 'Pengguna', roles: ['admin'] },
  ]
  return all.filter(item => item.roles.includes(auth.user?.role))
})

function isActive(to) {
  return route.path.startsWith(to)
}
</script>

<template>
  <aside
    class="fixed top-0 left-0 h-full bg-primary-950 text-white z-30 transition-all duration-300 flex flex-col shadow-2xl"
    :class="open ? 'w-64' : 'w-16'"
  >
    <!-- Logo -->
    <div class="flex items-center gap-3 px-4 py-5 border-b border-primary-800">
      <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
        A
      </div>
      <Transition name="fade">
        <span v-if="open" class="font-bold text-sm leading-tight text-white whitespace-nowrap">
          Apotek Algenz
        </span>
      </Transition>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
      <RouterLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 group"
        :class="isActive(item.to)
          ? 'bg-primary-600 text-white shadow-sm'
          : 'text-primary-300 hover:bg-primary-800 hover:text-white'"
      >
        <span class="text-lg flex-shrink-0">{{ item.icon }}</span>
        <Transition name="fade">
          <span v-if="open" class="whitespace-nowrap">{{ item.label }}</span>
        </Transition>
      </RouterLink>
    </nav>

    <!-- User info at bottom -->
    <div class="px-3 py-4 border-t border-primary-800">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold">
          {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
        </div>
        <Transition name="fade">
          <div v-if="open" class="min-w-0">
            <p class="text-xs font-semibold text-white truncate">{{ auth.user?.name }}</p>
            <p class="text-xs text-primary-400 capitalize">{{ auth.user?.role_display || auth.user?.role }}</p>
          </div>
        </Transition>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
