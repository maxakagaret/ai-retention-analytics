<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">AI usage (last 30 days)</div>

    <q-card v-if="usage" flat bordered class="q-mb-md">
      <q-card-section>
        <div class="row items-center">
          <div class="text-subtitle1">Monthly budget</div>
          <q-space />
          <div>${{ usage.month_spent_usd.toFixed(4) }} of ${{ usage.month_budget_usd.toFixed(2) }}</div>
        </div>
        <q-linear-progress
          class="q-mt-sm"
          size="10px"
          rounded
          :value="usage.month_spent_usd / usage.month_budget_usd"
          :color="usage.month_spent_usd / usage.month_budget_usd > 0.8 ? 'red-7' : 'teal-7'"
        />
        <div class="text-caption text-grey-7 q-mt-xs">Generation is refused once the budget is spent; failed attempts are billed too.</div>
      </q-card-section>
    </q-card>

    <q-table v-if="usage" flat bordered :rows="usage.by_purpose" :columns="columns" row-key="purpose" hide-pagination />

    <div class="text-subtitle1 q-mt-lg q-mb-sm">Latest recommendations</div>
    <q-list bordered separator>
      <q-item v-for="rec in recs" :key="rec.id" clickable :to="`/segments/${rec.segment}`">
        <q-item-section>
          <q-item-label>{{ rec.segment_label }}</q-item-label>
          <q-item-label caption>{{ rec.summary ?? rec.error ?? '…' }}</q-item-label>
        </q-item-section>
        <q-item-section side><q-badge :color="rec.status === 'ready' ? 'teal-7' : rec.status === 'failed' ? 'red-7' : 'grey-6'" :label="rec.status" /></q-item-section>
      </q-item>
    </q-list>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import type { QTableColumn } from 'quasar'
import { api } from '../api/client'
import type { AiUsage, Recommendation } from '../api/types'

const usage = ref<AiUsage | null>(null)
const recs = ref<Recommendation[]>([])

const columns: QTableColumn<AiUsage['by_purpose'][number]>[] = [
  { name: 'purpose', label: 'Purpose', field: 'purpose', align: 'left' },
  { name: 'model', label: 'Model', field: 'model', align: 'left' },
  { name: 'calls', label: 'Calls', field: 'calls' },
  { name: 'failures', label: 'Rejected / failed', field: 'failures' },
  { name: 'in', label: 'Input tokens', field: 'input_tokens' },
  { name: 'out', label: 'Output tokens', field: 'output_tokens' },
  { name: 'cost', label: 'Cost', field: 'cost_usd', format: (v: number) => `$${v.toFixed(4)}` },
  { name: 'latency', label: 'Avg latency', field: 'avg_latency_ms', format: (v: number) => `${v} ms` },
]

onMounted(async () => {
  ;[usage.value, recs.value] = await Promise.all([api.aiUsage(), api.recommendations()])
})
</script>
