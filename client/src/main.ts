import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import { applyAppearance } from './composables/useAppearance'
import router from './router'

applyAppearance()

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
