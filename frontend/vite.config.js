import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig(({ mode }) => {
  // Load environment variables including system ones
  const env = loadEnv(mode, process.cwd(), '')
  const backendUrl = env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'

  return {
    plugins: [vue()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      port: 5174,
      host: true,
      allowedHosts: ['alklinik.1vcent.my.id'],
      proxy: {
        '/api': {
          target: backendUrl,
          changeOrigin: true,
          secure: false,
          withCredentials: true,
        },
        '/sanctum': {
          target: backendUrl,
          changeOrigin: true,
          secure: false,
          withCredentials: true,
        },
      },
    },
  }
})
