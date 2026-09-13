import { createApp } from 'vue'
import App from './App.vue'

const mountPoint = document.getElementById('byebyemoneylist')
const appVersion = mountPoint?.dataset.version ?? ''

const app = createApp(App)
app.provide('appVersion', appVersion)
app.mount('#byebyemoneylist')
