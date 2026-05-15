import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from 'axios'
import router from './router/index.js'
import App from './App.vue'
import { vWrite } from './directives/vWrite.js'
import './assets/styles/main.scss'

const BASE = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002'

// Fetch CSRF cookie once on app startup — required by Laravel Sanctum stateful auth
axios.get(`${BASE}/sanctum/csrf-cookie`, { withCredentials: true })
  .catch(() => { /* silently ignore if backend not reachable */ })

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.directive('write', vWrite)
app.mount('#app')
