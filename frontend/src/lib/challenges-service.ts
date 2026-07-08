import { api } from '@/lib/api'

export interface ChallengeRules {
  profit_target_phase1_pct: number
  profit_target_phase2_pct: number | null
  max_daily_drawdown_pct: number
  max_total_drawdown_pct: number
  min_trading_days: number
  profit_split_pct: number
  news_trading_restricted: boolean
  weekend_holding_allowed: boolean
}

export interface Challenge {
  id: string
  name: string
  slug: string
  phase_count: number
  account_size: number
  currency: string
  price: number
  rules: ChallengeRules
}

export interface Order {
  id: string
  reference: string
  challenge: Challenge
  subtotal: number
  discount_amount: number
  total: number
  currency: string
  status: 'pending' | 'paid' | 'failed' | 'expired' | 'refunded'
  paid_at: string | null
  expires_at: string
}

export interface TradingAccount {
  id: string
  mt5_login: number | null
  mt5_server: string | null
  phase: 'evaluation_1' | 'evaluation_2' | 'funded'
  status: 'provisioning' | 'active' | 'passed' | 'failed' | 'breached' | 'funded' | 'disabled'
  account_size: number
  current_balance: number | null
  current_equity: number | null
  trading_days_count: number
  challenge: Challenge
  provisioned_at: string | null
  breached_at: string | null
  breach_reason: string | null
}

export const challengesService = {
  async list() {
    const { data } = await api.get<{ data: Challenge[] }>('/challenges')
    return data.data
  },
  async get(id: string) {
    const { data } = await api.get<{ data: Challenge }>(`/challenges/${id}`)
    return data.data
  },
}

export const checkoutService = {
  async checkout(challengeId: string, couponCode?: string) {
    const { data } = await api.post<{ data: { order: Order; authorization_url: string | null } }>(
      '/checkout',
      { challenge_id: challengeId, coupon_code: couponCode || undefined },
    )
    return data.data
  },
  async pollStatus(reference: string) {
    const { data } = await api.get<{ data: Order }>(`/checkout/${reference}`)
    return data.data
  },
  async orderHistory() {
    const { data } = await api.get<{ data: Order[] }>('/orders')
    return data.data
  },
}

export const tradingAccountsService = {
  async list() {
    const { data } = await api.get<{ data: TradingAccount[] }>('/trading-accounts')
    return data.data
  },
  async credentials(id: string) {
    const { data } = await api.get<{
      data: { mt5_login: number; mt5_password: string; mt5_server: string }
    }>(`/trading-accounts/${id}/credentials`)
    return data.data
  },
}
