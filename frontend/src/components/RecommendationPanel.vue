<template>
  <q-card flat bordered>
    <q-card-section class="row items-center">
      <q-icon name="psychology" color="teal-8" size="sm" class="q-mr-sm" />
      <div class="text-subtitle1">AI recommendations</div>
      <q-space />
      <q-btn
        unelevated
        dense
        no-caps
        color="teal-8"
        :icon="rec ? 'refresh' : 'auto_awesome'"
        :label="rec ? 'Regenerate' : 'Generate'"
        :loading="store.generating[segment]"
        @click="generate"
      />
    </q-card-section>

    <q-card-section v-if="rec?.status === 'failed'" class="q-pt-none">
      <q-banner dense class="bg-red-1 text-red-9">{{ rec.error }}</q-banner>
    </q-card-section>

    <template v-if="rec?.status === 'ready'">
      <q-card-section class="q-pt-none text-grey-8">{{ rec.summary }}</q-card-section>
      <q-list separator>
        <q-item v-for="(item, i) in rec.items" :key="i">
          <q-item-section avatar><q-icon :name="channelIcon[item.channel]" color="teal-8" /></q-item-section>
          <q-item-section>
            <q-item-label class="text-weight-medium">
              {{ item.title }}
              <q-badge :color="impactColor[item.expected_impact]" :label="item.expected_impact" class="q-ml-xs" />
            </q-item-label>
            <q-item-label caption>{{ item.rationale }}</q-item-label>
            <q-item-label caption>
              <b>When:</b> {{ item.timing }} · <b>Offer:</b> {{ item.offer }} · <b>Target:</b> {{ pct(item.target_share) }} of the segment
            </q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
      <q-expansion-item dense icon="data_object" label="What the model saw (aggregates only, no personal data)" header-class="text-grey-7">
        <pre class="context">{{ JSON.stringify(rec.context, null, 2) }}</pre>
      </q-expansion-item>
    </template>

    <q-card-section v-if="!rec" class="q-pt-none text-grey-7">
      Not generated yet. The model gets segment aggregates and returns campaigns, validated against a schema.
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useQuasar } from 'quasar'
import { errorText } from '../api/client'
import type { SegmentKey } from '../api/types'
import { channelIcon, pct } from '../format'
import { useRecommendationsStore } from '../stores/recommendations'

const props = defineProps<{ segment: SegmentKey }>()
const store = useRecommendationsStore()
const $q = useQuasar()
const rec = computed(() => store.bySegment[props.segment])
const impactColor = { low: 'grey-6', medium: 'blue-6', high: 'teal-8' }

async function generate() {
  try {
    await store.generate(props.segment, !!rec.value)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  }
}
</script>

<style scoped>
.context { font-size: 11px; background: #f5f5f5; padding: 8px; margin: 0 16px 16px; border-radius: 4px; overflow-x: auto; }
</style>
