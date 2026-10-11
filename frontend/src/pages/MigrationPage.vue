<template>
  <q-page padding>
    <div class="text-h5">Segment migration</div>
    <div v-if="data" class="text-grey-7 q-mb-md">
      Where customers of the {{ data.from_as_of }} snapshot are on {{ data.to_as_of }}. Diagonal = stayed; right of it = moved.
    </div>
    <q-banner v-if="error" class="bg-grey-2">{{ error }}</q-banner>

    <div v-if="data" class="matrix-wrap">
      <table class="matrix">
        <thead>
          <tr>
            <th>from \ to</th>
            <th v-for="to in keys" :key="to">{{ short(to) }}</th>
            <th>became inactive</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="from in keys" :key="from">
            <th class="text-left">{{ short(from) }}</th>
            <td
              v-for="to in keys"
              :key="to"
              :class="{ diag: from === to }"
              :style="{ background: cellColor(data.matrix[from][to], from === to) }"
            >
              {{ data.matrix[from][to] || '' }}
            </td>
            <td>{{ data.churned_out[from] ?? '' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, errorText } from '../api/client'
import type { Migration, SegmentKey } from '../api/types'

const data = ref<Migration | null>(null)
const error = ref<string | null>(null)
const keys = computed(() => Object.keys(data.value?.matrix ?? {}) as SegmentKey[])
const max = computed(() => Math.max(1, ...Object.values(data.value?.matrix ?? {}).flatMap((row) => Object.values(row))))

const short = (key: string) => key.replace(/_/g, ' ')
const cellColor = (n: number, diagonal: boolean) =>
  n ? `rgba(${diagonal ? '0,121,107' : '230,81,0'}, ${0.12 + 0.6 * (n / max.value)})` : 'transparent'

onMounted(async () => {
  try {
    data.value = await api.migration()
  } catch (e) {
    error.value = errorText(e)
  }
})
</script>

<style scoped>
.matrix-wrap { overflow-x: auto; }
.matrix { border-collapse: collapse; font-size: 12px; }
.matrix th, .matrix td { border: 1px solid #e0e0e0; padding: 6px 8px; text-align: center; min-width: 64px; }
.matrix thead th { text-transform: capitalize; }
.matrix tbody th { text-transform: capitalize; white-space: nowrap; }
.diag { font-weight: 700; }
</style>
