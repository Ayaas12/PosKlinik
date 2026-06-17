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
        path: 'kasir',
        name: 'Kasir',
        component: () => import('@/views/KasirView.vue'),
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

export default router
