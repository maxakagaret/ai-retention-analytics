import { defineStore } from 'pinia'
import { api } from '../api/client'
import type { Recommendation, SegmentKey } from '../api/types'

/**
 * Generation is asynchronous on the backend (queued LLM call). The store starts
 * it and polls the record until it is ready or failed.
 */
export const useRecommendationsStore = defineStore('recommendations', {
  state: () => ({
    bySegment: {} as Partial<Record<SegmentKey, Recommendation>>,
    generating: {} as Partial<Record<SegmentKey, boolean>>,
  }),

  actions: {
    async load() {
      for (const rec of await api.recommendations()) this.bySegment[rec.segment] = rec
    },

    async generate(segment: SegmentKey, force = false) {
      this.generating[segment] = true
      try {
        let rec = await api.generateRecommendation(segment, force)
        for (let i = 0; i < 30 && rec.status === 'pending'; i++) {
          await new Promise((resolve) => setTimeout(resolve, 1000))
          rec = await api.recommendation(rec.id)
        }
        this.bySegment[segment] = rec
        return rec
      } finally {
        this.generating[segment] = false
      }
    },
  },
})
