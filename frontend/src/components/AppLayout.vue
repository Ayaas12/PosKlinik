<script setup>
import { ref, onMounted } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import Sidebar from './Sidebar.vue'
import Navbar from './Navbar.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const sidebarOpen = ref(true)

onMounted(async () => {
  if (!auth.isLoggedIn) {
    router.push('/login')
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <Sidebar :open="sidebarOpen" @toggle="sidebarOpen = !sidebarOpen" />

    <!-- Main content -->
    <div class="flex-1 flex flex-col min-w-0 transition-all duration-300"
         :class="sidebarOpen ? 'ml-64' : 'ml-16'">
      <Navbar @toggle-sidebar="sidebarOpen = !sidebarOpen" />
      <main class="flex-1 p-6 overflow-auto">
        <RouterView />
      </main>
    </div>
  </div>
</template>
