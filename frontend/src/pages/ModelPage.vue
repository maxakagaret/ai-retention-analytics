<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div>
        <div class="text-h5">Churn model quality</div>
        <div class="text-caption text-grey-7">
          The model is replayed at a past date with only the data known then, and compared with what actually happened.
        </div>
      </div>
      <q-space />
      <q-btn unelevated color="teal-8" icon="science" label="Run backtest" :loading="running" @click="run" />
    </div>

    <template v-if="bt">
      <div class="row q-col-gutter-md q-mb-md">
        <div v-for="kpi in kpis" :key="kpi.label" class="col-6 col-md-3">
          <q-card flat bordered><q-card-section>
            <div class="text-caption text-grey-7">{{ kpi.label }}</div>
            <div class="text-h5">{{ kpi.value }}</div>
            <div class="text-caption text-grey-6">{{ kpi.hint }}</div>
          </q-card-section></q-card>
        </div>
      </div>

      <q-card flat bordered>
        <q-card-section class="text-subtitle1">Calibration: actual churn rate by predicted risk</q-card-section>
        <q-card-section>
          <div class="deciles">
            <div v-for="d in bt.deciles" :key="d.decile" class="column items-center col">
              <div class="text-caption">{{ d.actual_rate === null ? '' : pct(d.actual_rate) }}</div>
              <div class="bar bg-teal-7" :style="{ height: `${(d.actual_rate ?? 0) * 160}px` }" />
              <div class="text-caption text-grey-7">{{ d.decile }}</div>
              <div class="text-caption text-grey-5">n={{ d.customers }}</div>
            </div>
          </div>
          <div class="text-caption text-grey-7 q-mt-sm">A useful score climbs from left to right: higher predicted risk ⇒ more customers really did not come back.</div>
        </q-card-section>
      </q-card>
    </template>
    <q-banner v-else class="bg-grey-2">No backtest yet.</q-banner>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api, errorText } from '../api/client'
import type { Backtest } from '../api/types'
import { pct } from '../format'

const $q = useQuasar()
const bt = ref<Backtest | null>(null)
const running = ref(false)

const kpis = computed(() => [
  { label: 'ROC AUC (model)', value: bt.value?.auc?.toFixed(3) ?? '—', hint: '0.5 = coin flip, 1.0 = perfect' },
  { label: 'ROC AUC (recency only)', value: bt.value?.baseline_recency_auc?.toFixed(3) ?? '—', hint: 'naive baseline to beat' },
  { label: 'Precision@100', value: pct(bt.value?.precision_at_100), hint: 'top-100 risk list that really churned' },
  { label: 'Churn rate', value: pct(bt.value?.churn_rate), hint: `${bt.value?.customers} customers, cutoff ${bt.value?.cutoff}, ${bt.value?.horizon_days}d horizon` },
])

async function run() {
  running.value = true
  try {
    bt.value = await api.runBacktest()
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  } finally {
    running.value = false
  }
}

onMounted(async () => { bt.value = (await api.overview()).backtest })
</script>

<style scoped>
.deciles { display: flex; align-items: flex-end; gap: 8px; }
.bar { width: 70%; min-height: 2px; border-radius: 3px 3px 0 0; }
</style>
