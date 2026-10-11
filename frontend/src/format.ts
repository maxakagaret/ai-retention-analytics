import type { SegmentKey } from './api/types'

export const money = (v: number | null | undefined) =>
  v === null || v === undefined ? '—' : `$${Math.round(v).toLocaleString()}`

export const pct = (v: number | null | undefined, digits = 0) =>
  v === null || v === undefined ? '—' : `${(v * 100).toFixed(digits)}%`

export const riskColor = (risk: number) => (risk >= 70 ? 'red-7' : risk >= 40 ? 'orange-8' : 'green-7')

export const segmentColor: Record<SegmentKey, string> = {
  champions: 'green-8',
  loyal: 'green-6',
  potential_loyalist: 'teal-6',
  new_customers: 'cyan-7',
  promising: 'light-blue-6',
  need_attention: 'amber-8',
  at_risk: 'orange-9',
  cant_lose_them: 'red-8',
  hibernating: 'blue-grey-6',
  lost: 'grey-7',
}

export const channelIcon: Record<string, string> = { sms: 'sms', email: 'mail', messenger: 'chat', call: 'call' }
