import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { Quasar, Notify, Dialog } from 'quasar'
import '@quasar/extras/material-icons/material-icons.css'
import 'quasar/dist/quasar.css'

import App from './App.vue'
import { router } from './router'

createApp(App)
  .use(Quasar, { plugins: { Notify, Dialog } })
  .use(createPinia())
  .use(router)
  .mount('#app')
