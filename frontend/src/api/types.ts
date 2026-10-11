export type SegmentKey =
  | 'champions' | 'loyal' | 'potential_loyalist' | 'new_customers' | 'promising'
  | 'need_attention' | 'at_risk' | 'cant_lose_them' | 'hibernating' | 'lost'

export interface SegmentSummary {
  label: string
  customers: number
  revenue: number
  revenue_share: number
  avg_churn_risk: number | null
  revenue_at_risk: number
}

export interface RunSummary {
  customers: number
  revenue: number
  revenue_at_risk: number
  high_risk_customers: number
  segments: Record<SegmentKey, SegmentSummary>
  risk_histogram: number[]
}

export interface Backtest {
  cutoff: string
  horizon_days: number
  customers: number
  churn_rate: number | null
  auc: number | null
  baseline_recency_auc: number | null
  deciles: { decile: string; customers: number; actual_rate: number | null }[]
  precision_at_100: number | null
}

export interface Overview {
  run: { id: number; as_of: string; summary: RunSummary; params: Record<string, unknown> } | null
  backtest: Backtest | null
}

export interface Segment extends Partial<SegmentSummary> {
  key: SegmentKey
  label: string
  goal: string
  retention_priority: boolean
}

export interface CustomerRow {
  customer_id: number
  name: string
  synthetic_profile: string
  rfm: string
  recency_days: number
  frequency: number
  monetary: number
  churn_risk: number
  top_factor: string | null
  next_visit_due: string
  revenue_at_risk: number
}

export interface RiskFactor {
  key: string
  label: string
  impact: number
}

export interface CustomerProfile {
  id: number
  name: string
  joined_on: string
  synthetic_profile: string
  score: {
    segment: SegmentKey
    segment_label: string
    rfm: { r: number; f: number; m: number }
    recency_days: number
    frequency: number
    monetary: number
    churn_risk: number
    risk_factors: RiskFactor[]
    expected_interval_days: number
    next_visit_due: string
    revenue_at_risk: number
  } | null
  history: { as_of: string; segment: SegmentKey; churn_risk: number }[]
  visits: { date: string; total: number; services: string[] }[]
}

export interface RecommendationItem {
  title: string
  rationale: string
  channel: 'sms' | 'email' | 'messenger' | 'call'
  timing: string
  offer: string
  target_share: number
  expected_impact: 'low' | 'medium' | 'high'
}

export interface Recommendation {
  id: number
  segment: SegmentKey
  segment_label: string
  status: 'pending' | 'ready' | 'failed'
  summary: string | null
  items: RecommendationItem[]
  context: Record<string, unknown> | null
  model: string | null
  error: string | null
  updated_at: string | null
}

export interface Migration {
  from_run: number
  to_run: number
  from_as_of: string
  to_as_of: string
  matrix: Record<SegmentKey, Record<SegmentKey, number>>
  churned_out: Partial<Record<SegmentKey, number>>
}

export interface AiUsage {
  since: string
  by_purpose: { purpose: string; model: string; calls: number; failures: number; input_tokens: number; output_tokens: number; cost_usd: number; avg_latency_ms: number }[]
  month_spent_usd: number
  month_budget_usd: number
}
