<script setup>
import { ref, onMounted } from 'vue'
import api from '@/utils/api'
import { formatDateTime } from '@/utils/format'

const users = ref([])
const loading = ref(true)
const showModal = ref(false)
const modalMode = ref('add')
const form = ref({})
const saving = ref(false)
const formError = ref('')

async function fetchUsers() {
  loading.value = true
  try {
    const res = await api.get('/users')
    users.value = res.data.data || res.data
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

onMounted(fetchUsers)

function openAdd() {
  form.value = { name: '', email: '', password: '', password_confirmation: '', role: 'kasir', is_active: true }
  formError.value = ''
  modalMode.value = 'add'
  showModal.value = true
}

function openEdit(user) {
  form.value = { ...user, password: '', password_confirmation: '', role: user.role?.name || user.role }
  formError.value = ''
  modalMode.value = 'edit'
  showModal.value = true
}

async function saveForm() {
  saving.value = true
  formError.value = ''
  try {
    if (modalMode.value === 'add') {
      await api.post('/users', form.value)
    } else {
      const payload = { ...form.value }
      if (!payload.password) { delete payload.password; delete payload.password_confirmation }
      await api.put(`/users/${form.value.id}`, payload)
    }
    showModal.value = false
    await fetchUsers()
  } catch (e) {
    const errs = e.response?.data?.errors
    if (errs) {
      formError.value = Object.values(errs).flat().join(' | ')
    } else {
      formError.value = e.response?.data?.message || 'Terjadi kesalahan.'
    }
  } finally {
    saving.value = false
  }
}

async function toggleActive(user) {
  try {
    await api.put(`/users/${user.id}`, { ...user, is_active: !user.is_active, role: user.role?.name || user.role })
    user.is_active = !user.is_active
  } catch (_) {}
}

function roleLabel(role) {
  const r = typeof role === 'object' ? role?.name : role
  return { admin: 'Admin', apoteker: 'Apoteker', kasir: 'Kasir' }[r] || r
}
function roleClass(role) {
  const r = typeof role === 'object' ? role?.name : role
  return { admin: 'badge-red', apoteker: 'badge-blue', kasir: 'badge-green' }[r] || 'badge-gray'
}
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-wrap gap-3 items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Manajemen Pengguna</h2>
        <p class="text-sm text-gray-500">Kelola akun admin, apoteker, dan kasir</p>
      </div>
      <button @click="openAdd" class="btn-primary">+ Tambah Pengguna</button>
    </div>

    <!-- Users Grid -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 4" :key="i" class="card p-5 animate-pulse space-y-3">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-full bg-gray-200"></div>
          <div class="flex-1 space-y-2">
            <div class="h-4 bg-gray-200 rounded w-2/3"></div>
            <div class="h-3 bg-gray-200 rounded w-1/2"></div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="user in users" :key="user.id"
           class="card p-5 hover:shadow-md transition-all duration-150">
        <!-- Avatar & Info -->
        <div class="flex items-center gap-4 mb-4">
          <div class="w-12 h-12 rounded-full flex items-center justify-center text-lg font-bold text-white flex-shrink-0"
               :class="{ admin: 'bg-red-500', apoteker: 'bg-blue-500', kasir: 'bg-green-500' }[typeof user.role === 'object' ? user.role?.name : user.role] || 'bg-primary-500'"
          >
            {{ user.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div class="min-w-0">
            <p class="font-semibold text-gray-900 truncate">{{ user.name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
          </div>
        </div>

        <!-- Badges -->
        <div class="flex items-center justify-between mb-4">
          <span :class="roleClass(user.role)">{{ roleLabel(user.role) }}</span>
          <span :class="user.is_active ? 'badge-green' : 'badge-red'">
            {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
        </div>

        <!-- Meta -->
        <p class="text-xs text-gray-400 mb-4">Dibuat: {{ formatDateTime(user.created_at) }}</p>

        <!-- Actions -->
        <div class="flex gap-2">
          <button @click="openEdit(user)" class="btn-secondary flex-1 justify-center text-xs py-1.5">✏️ Edit</button>
          <button
            @click="toggleActive(user)"
            class="flex-1 justify-center text-xs py-1.5 rounded-lg font-medium border transition-all"
            :class="user.is_active
              ? 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100'
              : 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'"
          >
            {{ user.is_active ? '🚫 Nonaktifkan' : '✓ Aktifkan' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Transition name="modal">
      <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="bg-primary-700 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-white font-semibold">{{ modalMode === 'add' ? '+ Tambah Pengguna' : '✏️ Edit Pengguna' }}</h3>
            <button @click="showModal = false" class="text-primary-200 hover:text-white text-xl">✕</button>
          </div>
          <div class="p-6 space-y-4">
            <div v-if="formError" class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ formError }}</div>

            <div>
              <label class="form-label">Nama Lengkap *</label>
              <input v-model="form.name" type="text" class="form-input" placeholder="Nama pengguna" />
            </div>
            <div>
              <label class="form-label">Email *</label>
              <input v-model="form.email" type="email" class="form-input" placeholder="email@apotek.com" />
            </div>
            <div>
              <label class="form-label">Role *</label>
              <select v-model="form.role" class="form-select">
                <option value="admin">Admin</option>
                <option value="apoteker">Apoteker</option>
                <option value="kasir">Kasir</option>
              </select>
            </div>
            <div>
              <label class="form-label">{{ modalMode === 'add' ? 'Password *' : 'Password Baru (kosongkan jika tidak diubah)' }}</label>
              <input v-model="form.password" type="password" class="form-input" placeholder="Min. 8 karakter" />
            </div>
            <div>
              <label class="form-label">Konfirmasi Password</label>
              <input v-model="form.password_confirmation" type="password" class="form-input" placeholder="Ulangi password" />
            </div>
            <div class="flex items-center gap-2">
              <input v-model="form.is_active" id="is-active" type="checkbox" class="w-4 h-4 text-primary-600 rounded border-gray-300" />
              <label for="is-active" class="text-sm text-gray-700">Akun Aktif</label>
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
  </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
