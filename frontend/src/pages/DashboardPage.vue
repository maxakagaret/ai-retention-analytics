<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div>
        <div class="text-h5">Customer base</div>
        <div v-if="overview?.run" class="text-caption text-grey-7">snapshot as of {{ overview.run.as_of }}</div>
      </div>
      <q-space />
      <q-btn unelevated color="teal-8" icon="play_arrow" label="Run analysis now" :loading="running" @click="run" />
    </div>

    <template v-if="summary">
      <div class="row q-col-gutter-md q-mb-md">
        <div v-for="kpi in kpis" :key="kpi.label" class="col-6 col-md-3">
          <q-card flat bordered><q-card-section>
            <div class="text-caption text-grey-7">{{ kpi.label }}</div>
            <div class="text-h5" :class="kpi.class">{{ kpi.value }}</div>
            <div v-if="kpi.hint" class="text-caption text-grey-6">{{ kpi.hint }}</div>
          </q-card-section></q-card>
        </div>
      </div>

      <div class="text-subtitle1 q-mb-sm">RFM segments</div>
      <div class="row q-col-gutter-md">
        <div v-for="s in segments" :key="s.key" class="col-12 col-sm-6 col-lg-4">
          <q-card flat bordered class="cursor-pointer segment" @click="$router.push(`/segments/${s.key}`)">
            <q-card-section>
              <div class="row items-center">
                <q-badge :color="segmentColor[s.key]" class="q-mr-sm">&nbsp;</q-badge>
                <div class="text-subtitle1 text-weight-medium">{{ s.label }}</div>
                <q-icon v-if="s.retention_priority" name="priority_high" color="orange-9" class="q-ml-xs">
                  <q-tooltip>Retention priority</q-tooltip>
                </q-icon>
                <q-space />
                <div class="text-h6">{{ s.customers ?? 0 }}</div>
              </div>
              <div class="text-caption text-grey-7 q-mb-sm">{{ s.goal }}</div>
              <div class="row text-caption">
                <div class="col">revenue <b>{{ pct(s.revenue_share) }}</b></div>
                <div class="col">avg risk <b :class="`text-${riskColor(s.avg_churn_risk ?? 0)}`">{{ s.avg_churn_risk ?? '—' }}</b></div>
                <div class="col text-right">at risk <b>{{ money(s.revenue_at_risk) }}</b></div>
              </div>
              <q-linear-progress :value="s.revenue_share ?? 0" :color="segmentColor[s.key]" class="q-mt-xs" rounded />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <q-card flat bordered class="q-mt-md">
        <q-card-section class="text-subtitle1">Churn risk distribution</q-card-section>
        <q-card-section>
          <div class="histogram">
            <div v-for="(count, i) in summary.risk_histogram" :key="i" class="column items-center col">
              <div class="text-caption">{{ count }}</div>
              <div class="bar" :class="`bg-${riskColor(i * 10)}`" :style="{ height: `${(count / maxBin) * 140}px` }" />
              <div class="text-caption text-grey-7">{{ i * 10 }}–{{ i * 10 + 9 }}</div>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </template>

    <q-banner v-else-if="overview" class="bg-grey-2">No analysis yet — run one.</q-banner>
    <q-inner-loading :showing="!overview" />
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api, errorText } from '../api/client'
import type { Overview, Segment } from '../api/types'
import { money, pct, riskColor, segmentColor } from '../format'

const $q = useQuasar()
const overview = ref<Overview | null>(null)
const segments = ref<Segment[]>([])
const running = ref(false)

const summary = computed(() => overview.value?.run?.summary ?? null)
const maxBin = computed(() => Math.max(1, ...(summary.value?.risk_histogram ?? [1])))
const kpis = computed(() => {
  const s = summary.value!
  const bt = overview.value?.backtest
  return [
    { label: 'Active customers', value: s.customers.toLocaleString() },
    { label: 'Revenue, last 12 months', value: money(s.revenue) },
    { label: 'Revenue at risk', value: money(s.revenue_at_risk), class: 'text-red-8', hint: `${s.high_risk_customers} customers with risk ≥ 70` },
    { label: 'Churn model ROC AUC', value: bt?.auc?.toFixed(2) ?? '—', hint: bt ? `recency-only baseline ${bt.baseline_recency_auc?.toFixed(2)}` : 'run a backtest' },
  ]
})

async function load() {
  ;[overview.value, segments.value] = await Promise.all([api.overview(), api.segments()])
}

async function run() {
  running.value = true
  try {
    await api.runAnalysis()
    await load()
    $q.notify({ type: 'positive', message: 'New snapshot stored' })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  } finally {
    running.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.segment:hover { border-color: #00796b; }
.histogram { display: flex; align-items: flex-end; gap: 8px; }
.bar { width: 70%; min-height: 2px; border-radius: 3px 3px 0 0; }
</style>
