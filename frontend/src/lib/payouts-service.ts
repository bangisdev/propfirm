import { api } from '@/lib/api'

export interface BankAccount {
  id: string
  bank_name: string
  account_name: string
  masked_account_number: string
  currency: string
  is_default: boolean
  created_at: string
}

export interface PayoutRequestRecord {
  id: string
  trading_account_id: string
  requested_amount: number
  profit_split_pct: number
  trader_amount: number
  firm_amount: number
  currency: string
  status: 'pending' | 'approved' | 'rejected' | 'processing' | 'paid' | 'failed'
  admin_notes: string | null
  bank_account: BankAccount
  reviewed_at: string | null
  paid_at: string | null
  created_at: string
}

export const payoutsService = {
  async listBankAccounts() {
    const { data } = await api.get<{ data: BankAccount[] }>('/payout-bank-accounts')
    return data.data
  },
  async addBankAccount(payload: {
    bank_name: string
    bank_code: string
    account_number: string
    currency?: string
  }) {
    const { data } = await api.post<{ data: BankAccount }>('/payout-bank-accounts', payload)
    return data.data
  },
  async listRequests() {
    const { data } = await api.get<{ data: PayoutRequestRecord[] }>('/payout-requests')
    return data.data
  },
  async requestPayout(payload: { trading_account_id: string; bank_account_id: string; amount: number }) {
    const { data } = await api.post<{ data: PayoutRequestRecord }>('/payout-requests', payload)
    return data.data
  },
}
