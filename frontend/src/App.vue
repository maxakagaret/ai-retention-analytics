<template>
  <q-layout view="hHh lpR fFf">
    <q-header elevated class="bg-teal-9 text-white">
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="drawer = !drawer" />
        <q-toolbar-title>Retention Analytics</q-toolbar-title>
        <q-badge color="amber-8" text-color="black" label="synthetic data · fake LLM" />
      </q-toolbar>
    </q-header>

    <q-drawer v-model="drawer" show-if-above bordered :width="220">
      <q-list padding>
        <q-item v-for="item in nav" :key="item.to" :to="item.to" exact clickable v-ripple>
          <q-item-section avatar><q-icon :name="item.icon" /></q-item-section>
          <q-item-section>{{ item.label }}</q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const drawer = ref(true)
const nav = [
  { to: '/', label: 'Segments', icon: 'donut_large' },
  { to: '/migration', label: 'Segment migration', icon: 'swap_horiz' },
  { to: '/model', label: 'Model quality', icon: 'insights' },
  { to: '/ai', label: 'AI usage', icon: 'psychology' },
]
</script>
