import { createRouter, createWebHistory } from 'vue-router'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: () => import('./pages/DashboardPage.vue') },
    // "key" is reserved in Vue, so the param is passed as segmentKey.
    { path: '/segments/:key', component: () => import('./pages/SegmentPage.vue'), props: (r) => ({ segmentKey: r.params.key }) },
    { path: '/migration', component: () => import('./pages/MigrationPage.vue') },
    { path: '/model', component: () => import('./pages/ModelPage.vue') },
    { path: '/ai', component: () => import('./pages/AiPage.vue') },
  ],
})
