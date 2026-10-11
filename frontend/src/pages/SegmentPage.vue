<template>
  <q-page padding>
    <div class="row items-center q-mb-sm">
      <q-btn flat round icon="arrow_back" to="/" />
      <div class="text-h5 q-ml-sm">{{ segment?.label }}</div>
      <q-badge v-if="segment" class="q-ml-sm" :color="segmentColor[segment.key]" :label="`${segment.customers ?? 0} customers`" />
    </div>
    <div class="text-grey-7 q-mb-md">{{ segment?.goal }}</div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-lg-7">
        <q-table
          flat
          bordered
          :rows="rows"
          :columns="columns"
          row-key="customer_id"
          v-model:pagination="pagination"
          :rows-number="total"
          binary-state-sort
          @request="onRequest"
          @row-click="(_, row) => { selected = row.customer_id; dialog = true }"
        >
          <template #body-cell-risk="{ row }">
            <q-td>
              <q-badge :color="riskColor(row.churn_risk)" :label="row.churn_risk" />
            </q-td>
          </template>
          <template #body-cell-factor="{ row }">
            <q-td class="factor">{{ row.top_factor ?? '—' }}</q-td>
          </template>
          <template #body-cell-profile="{ row }">
            <q-td><q-badge outline color="grey-7" :label="row.synthetic_profile" /></q-td>
          </template>
        </q-table>
        <div class="text-caption text-grey-6 q-mt-xs">
          "Profile" is the hidden ground truth of the synthetic generator — the model never sees it; it is shown to sanity-check the scores.
        </div>
      </div>
      <div class="col-12 col-lg-5">
        <RecommendationPanel :segment="key" />
      </div>
    </div>

    <CustomerDialog v-model="dialog" :customer-id="selected" />
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import type { QTableColumn, QTableProps } from 'quasar'
import { api } from '../api/client'
import type { CustomerRow, Segment, SegmentKey } from '../api/types'
import CustomerDialog from '../components/CustomerDialog.vue'
import RecommendationPanel from '../components/RecommendationPanel.vue'
import { money, riskColor, segmentColor } from '../format'
import { useRecommendationsStore } from '../stores/recommendations'

const props = defineProps<{ segmentKey: string }>()
const key = computed(() => props.segmentKey as SegmentKey)
const segment = ref<Segment | null>(null)
const rows = ref<CustomerRow[]>([])
const total = ref(0)
const pagination = ref({ page: 1, rowsPerPage: 25, rowsNumber: 0, sortBy: 'churn_risk', descending: true })
const dialog = ref(false)
const selected = ref<number | null>(null)

const columns: QTableColumn<CustomerRow>[] = [
  { name: 'name', label: 'Customer', field: 'name', align: 'left' },
  { name: 'risk', label: 'Risk', field: 'churn_risk', sortable: true },
  { name: 'factor', label: 'Main reason', field: 'top_factor', align: 'left' },
  { name: 'rfm', label: 'RFM', field: 'rfm' },
  { name: 'monetary', label: 'Spend (12m)', field: 'monetary', format: money, sortable: true },
  { name: 'at_risk', label: 'At risk', field: 'revenue_at_risk', format: money },
  { name: 'profile', label: 'Profile', field: 'synthetic_profile' },
]

async function load() {
  const sortField = pagination.value.sortBy === 'monetary' ? 'monetary' : 'churn_risk'
  const res = await api.segmentCustomers(key.value, pagination.value.page, sortField)
  rows.value = res.data
  total.value = res.meta.total
  pagination.value.rowsNumber = res.meta.total
}

const onRequest: QTableProps['onRequest'] = ({ pagination: p }) => {
  pagination.value = { ...pagination.value, page: p.page, sortBy: p.sortBy ?? 'churn_risk' }
  void load()
}

async function init() {
  segment.value = (await api.segments()).find((s) => s.key === key.value) ?? null
  pagination.value.page = 1
  await Promise.all([load(), useRecommendationsStore().load()])
}

onMounted(init)
watch(key, init)
</script>

<style scoped>
.factor { white-space: normal; max-width: 280px; font-size: 12px; }
</style>
