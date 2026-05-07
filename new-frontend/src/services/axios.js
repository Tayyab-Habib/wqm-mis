import axios from 'axios'

const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002'

const axiosInstance = axios.create({
  baseURL: BASE_URL,
  withCredentials: true,
  headers: {
    'Accept':       'application/json',
    'Content-Type': 'application/json',
  },
})

// Helper — read a cookie by name
function getCookie(name) {
  const match = document.cookie
    .split('; ')
    .find(row => row.startsWith(name + '='))
  return match ? decodeURIComponent(match.split('=')[1]) : null
}

// Request interceptor — attach Bearer token + XSRF token on every request
axiosInstance.interceptors.request.use(
  (config) => {
    // 1. Bearer token from localStorage
    const userStr = localStorage.getItem('user')
    if (userStr) {
      try {
        const user = JSON.parse(userStr)
        if (user?.token) {
          config.headers.Authorization = `Bearer ${user.token}`
        }
      } catch (e) { /* ignore */ }
    }

    // 2. XSRF token from cookie (required by EnsureFrontendRequestsAreStateful)
    const xsrf = getCookie('XSRF-TOKEN')
    if (xsrf) {
      config.headers['X-XSRF-TOKEN'] = xsrf
    }

    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor — unwrap data, handle 401
axiosInstance.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('user')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default axiosInstance
