import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { requiresGuest: true },
  },
  {
    path: '/kasir',
    name: 'Kasir',
    component: () => import('@/views/KasirView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/',
    component: () => import('@/components/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/dashboard',
      },
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/DashboardView.vue'),
      },
      {
        path: 'stok',
        name: 'Stok',
        component: () => import('@/views/StokView.vue'),
        meta: { roles: ['admin', 'apoteker'] },
      },
      {
        path: 'batch',
        name: 'Batch',
        component: () => import('@/views/BatchView.vue'),
        meta: { roles: ['admin', 'apoteker'] },
      },
      {
        path: 'laporan',
        name: 'Laporan',
        component: () => import('@/views/LaporanView.vue'),
        meta: { roles: ['admin', 'apoteker'] },
      },
      {
        path: 'pengguna',
        name: 'Pengguna',
        component: () => import('@/views/PenggunaView.vue'),
        meta: { roles: ['admin'] },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, _from, next) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return next('/login')
  }
  if (to.meta.requiresGuest && auth.isLoggedIn) {
    return next('/dashboard')
  }
  if (to.meta.roles && !to.meta.roles.includes(auth.user?.role)) {
    return next('/dashboard')
  }
  next()
})

// Handle global 401 events emitted by the API interceptor.
// Using the router (instead of window.location.href) avoids a full page
// reload that would wipe the Pinia store state.
window.addEventListener('auth:unauthorized', () => {
  const auth = useAuthStore()
  auth.user = null
  router.push('/login')
})

export default router
