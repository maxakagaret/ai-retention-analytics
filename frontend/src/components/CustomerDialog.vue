<template>
  <q-dialog v-model="open">
    <q-card style="width: 640px; max-width: 95vw">
      <template v-if="customer">
        <q-card-section class="row items-center">
          <div>
            <div class="text-h6">{{ customer.name }}</div>
            <div class="text-caption text-grey-7">
              customer since {{ customer.joined_on }} ·
              <q-badge outline color="grey-7" :label="`synthetic profile: ${customer.synthetic_profile}`" />
            </div>
          </div>
          <q-space />
          <q-circular-progress
            v-if="score"
            show-value
            :value="score.churn_risk"
            size="64px"
            :thickness="0.18"
            :color="riskColor(score.churn_risk)"
            track-color="grey-3"
          >
            {{ score.churn_risk }}
          </q-circular-progress>
        </q-card-section>

        <q-card-section v-if="score" class="q-pt-none">
          <div class="row q-gutter-sm q-mb-sm">
            <q-chip :color="segmentColor[score.segment]" text-color="white" :label="score.segment_label" />
            <q-chip outline :label="`RFM ${score.rfm.r}${score.rfm.f}${score.rfm.m}`" />
            <q-chip outline :label="`usual rhythm ${score.expected_interval_days}d`" />
            <q-chip outline :label="`next visit due ${score.next_visit_due}`" />
          </div>
          <div class="text-subtitle2">Why this risk score</div>
          <div v-for="factor in score.risk_factors" :key="factor.key" class="q-my-xs">
            <div class="row items-center">
              <div class="col">{{ factor.label }}</div>
              <div class="text-weight-medium">+{{ factor.impact }}</div>
            </div>
            <q-linear-progress :value="factor.impact / 100" :color="riskColor(score.churn_risk)" rounded />
          </div>
          <div class="text-caption text-grey-7 q-mt-sm">
            Annual spend {{ money(score.monetary) }} · {{ score.frequency }} visits in the last year · revenue at risk {{ money(score.revenue_at_risk) }}
          </div>
        </q-card-section>

        <q-card-section v-if="customer.history.length > 1" class="q-pt-none">
          <div class="text-subtitle2">Snapshots</div>
          <q-chip v-for="h in customer.history" :key="h.as_of" dense :color="segmentColor[h.segment]" text-color="white">
            {{ h.as_of }}: {{ h.segment }} · {{ h.churn_risk }}
          </q-chip>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <div class="text-subtitle2">Recent visits</div>
          <q-markup-table dense flat>
            <tbody>
              <tr v-for="v in customer.visits" :key="v.date">
                <td>{{ v.date }}</td>
                <td>{{ v.services.join(', ') }}</td>
                <td class="text-right">{{ money(v.total) }}</td>
              </tr>
            </tbody>
          </q-markup-table>
        </q-card-section>
      </template>
      <q-inner-loading :showing="!customer" />
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api } from '../api/client'
import type { CustomerProfile } from '../api/types'
import { money, riskColor, segmentColor } from '../format'

const open = defineModel<boolean>({ required: true })
const props = defineProps<{ customerId: number | null }>()
const customer = ref<CustomerProfile | null>(null)
const score = computed(() => customer.value?.score ?? null)

watch(() => [open.value, props.customerId] as const, async ([isOpen, id]) => {
  customer.value = null
  if (isOpen && id) customer.value = await api.customer(id)
})
</script>
