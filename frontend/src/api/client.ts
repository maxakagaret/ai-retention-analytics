import axios from 'axios'
import type {
  AiUsage, Backtest, CustomerProfile, CustomerRow, Migration, Overview, Recommendation, Segment, SegmentKey,
} from './types'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  headers: { Accept: 'application/json' },
})

/** Stateless API layer, one function per endpoint. */
export const api = {
  overview: () => http.get<{ data: Overview }>('/overview').then((r) => r.data.data),
  runAnalysis: () => http.post('/analysis/runs').then((r) => r.data.data),
  runBacktest: (horizonDays = 90) =>
    http.post<{ data: Backtest }>('/analysis/backtests', { horizon_days: horizonDays }).then((r) => r.data.data),
  migration: () => http.get<{ data: Migration }>('/analysis/migration').then((r) => r.data.data),

  segments: () => http.get<{ data: Segment[] }>('/segments').then((r) => r.data.data),
  segmentCustomers: (key: SegmentKey, page = 1, sort = 'churn_risk') =>
    http.get<{ data: CustomerRow[]; meta: { total: number } }>(`/segments/${key}/customers`, { params: { page, sort } })
      .then((r) => r.data),
  customer: (id: number) => http.get<{ data: CustomerProfile }>(`/customers/${id}`).then((r) => r.data.data),

  recommendations: () => http.get<{ data: Recommendation[] }>('/recommendations').then((r) => r.data.data),
  recommendation: (id: number) => http.get<{ data: Recommendation }>(`/recommendations/${id}`).then((r) => r.data.data),
  generateRecommendation: (key: SegmentKey, force = false) =>
    http.post<{ data: Recommendation }>(`/segments/${key}/recommendations`, { force }).then((r) => r.data.data),
  aiUsage: () => http.get<{ data: AiUsage }>('/ai/usage').then((r) => r.data.data),
}

export function errorText(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string } | undefined
    return data?.message ?? error.message
  }
  return String(error)
}
