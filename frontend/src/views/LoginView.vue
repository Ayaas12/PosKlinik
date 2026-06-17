<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const showPassword = ref(false)

async function handleLogin() {
  error.value = ''
  if (!email.value || !password.value) {
    error.value = 'Email dan password wajib diisi.'
    return
  }
  const result = await auth.login(email.value, password.value)
  if (result.success) {
    router.push('/dashboard')
  } else {
    error.value = result.message
  }
}

// Demo credentials helper
const demos = [
  { label: 'Admin', email: 'admin@apotekalgenz.com', password: 'Admin@12345' },
  { label: 'Apoteker', email: 'apoteker@apotekalgenz.com', password: 'Apotek@1234' },
  { label: 'Kasir', email: 'kasir@apotekalgenz.com', password: 'Kasir@12345' },
]
function fillDemo(d) {
  email.value = d.email
  password.value = d.password
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-950 via-primary-900 to-primary-800 flex items-center justify-center p-4">

    <!-- Background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-32 -right-32 w-96 h-96 bg-primary-600 rounded-full opacity-10 blur-3xl"></div>
      <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-secondary-500 rounded-full opacity-10 blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">
      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

        <!-- Header -->
        <div class="bg-gradient-to-r from-primary-700 to-primary-600 px-8 py-8 text-center">
          <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
            <span class="text-3xl">💊</span>
          </div>
          <h1 class="text-2xl font-bold text-white">Apotek Algenz</h1>
          <p class="text-primary-200 text-sm mt-1">Sistem Point of Sale</p>
        </div>

        <!-- Form -->
        <div class="px-8 py-8">
          <h2 class="text-lg font-semibold text-gray-800 mb-6">Masuk ke Akun Anda</h2>

          <!-- Error -->
          <Transition name="slide-down">
            <div v-if="error"
                 class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
              <span class="text-red-500 flex-shrink-0 mt-0.5">⚠️</span>
              <p class="text-sm text-red-700">{{ error }}</p>
            </div>
          </Transition>

          <form @submit.prevent="handleLogin" class="space-y-4">
            <!-- Email -->
            <div>
              <label class="form-label" for="email">Alamat Email</label>
              <input
                id="email"
                v-model="email"
                type="email"
                autocomplete="email"
                placeholder="contoh@apotek.com"
                class="form-input"
                :disabled="auth.loading"
              />
            </div>

            <!-- Password -->
            <div>
              <label class="form-label" for="password">Password</label>
              <div class="relative">
                <input
                  id="password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  placeholder="••••••••"
                  class="form-input pr-10"
                  :disabled="auth.loading"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                >
                  {{ showPassword ? '🙈' : '👁️' }}
                </button>
              </div>
            </div>

            <!-- Submit -->
            <button
              type="submit"
              :disabled="auth.loading"
              class="btn-primary w-full justify-center py-2.5 text-base"
            >
              <svg v-if="auth.loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              {{ auth.loading ? 'Memproses...' : 'Masuk' }}
            </button>
          </form>

          <!-- Demo credentials -->
          <div class="mt-6 pt-6 border-t border-gray-100">
            <p class="text-xs text-gray-500 text-center mb-3">Akun Demo (klik untuk mengisi)</p>
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="d in demos"
                :key="d.label"
                type="button"
                @click="fillDemo(d)"
                class="px-3 py-2 text-xs font-medium rounded-lg border border-gray-200 hover:bg-primary-50 hover:border-primary-300 hover:text-primary-700 transition-all text-gray-600"
              >
                {{ d.label }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <p class="text-center text-primary-300 text-xs mt-6">
        © 2024 Apotek Algenz. Semua hak dilindungi.
      </p>
    </div>
  </div>
</template>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
