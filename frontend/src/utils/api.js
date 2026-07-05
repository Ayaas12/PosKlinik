import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  withCredentials: false,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// Request interceptor – attach Bearer token from localStorage if available
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('pos_token')
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor – redirect to login on 401
// Skip the redirect if the request was the login endpoint itself
// (let the login form handle its own errors)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const isLoginRequest = error.config?.url?.includes('/login')
    if (error.response?.status === 401 && !isLoginRequest) {
      // Clear local session and signal the app to navigate to login
      localStorage.removeItem('pos_user')
      localStorage.removeItem('pos_token')
      // Use a custom event so the Vue router handles navigation
      // (avoids full page reload that wipes Pinia store)
      window.dispatchEvent(new CustomEvent('auth:unauthorized'))
    }
    return Promise.reject(error)
  }
)

export default api
