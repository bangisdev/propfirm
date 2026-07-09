import { api } from '@/lib/api'

export interface AffiliateStats {
  referral_code: string
  total_referrals: number
  converted_referrals: number
  total_earned: number
  total_pending: number
  total_processing: number
}

export interface Referral {
  name: string
  joined_at: string
  has_converted: boolean
}

export interface CommissionRecord {
  id: string
  referred_user_name: string
  order_amount: number
  commission_pct: number
  commission_amount: number
  currency: string
  status: 'pending' | 'processing' | 'paid' | 'failed'
  paid_at: string | null
  created_at: string
}

export const affiliateService = {
  async stats() {
    const { data } = await api.get<{ data: AffiliateStats }>('/affiliate/stats')
    return data.data
  },
  async referrals() {
    const { data } = await api.get<{ data: Referral[] }>('/affiliate/referrals')
    return data.data
  },
  async commissions() {
    const { data } = await api.get<{ data: CommissionRecord[] }>('/affiliate/commissions')
    return data.data
  },
}

export interface KycStatus {
  kyc_status: 'unverified' | 'pending' | 'verified' | 'rejected'
  latest_submission: {
    id: string
    document_type: string
    status: string
    rejection_reason: string | null
    submitted_at: string
  } | null
}

export const kycService = {
  async status() {
    const { data } = await api.get<{ data: KycStatus }>('/kyc')
    return data.data
  },
  async submit(documentType: string, front: File, back?: File, selfie?: File) {
    const formData = new FormData()
    formData.append('document_type', documentType)
    formData.append('document_front', front)
    if (back) formData.append('document_back', back)
    if (selfie) formData.append('selfie', selfie)

    const { data } = await api.post('/kyc', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
}

export interface TicketMessage {
  id: string
  message: string
  is_internal_note: boolean
  author: { id: string; name: string; role: string } | null
  created_at: string
}

export interface Ticket {
  id: string
  subject: string
  category: string
  priority: string
  status: 'open' | 'in_progress' | 'resolved' | 'closed'
  messages: TicketMessage[] | null
  last_reply_at: string | null
  created_at: string
}

export const supportService = {
  async list() {
    const { data } = await api.get<{ data: Ticket[] }>('/support-tickets')
    return data.data
  },
  async get(id: string) {
    const { data } = await api.get<{ data: Ticket }>(`/support-tickets/${id}`)
    return data.data
  },
  async create(payload: { subject: string; category: string; message: string }) {
    const { data } = await api.post<{ data: Ticket }>('/support-tickets', payload)
    return data.data
  },
  async reply(id: string, message: string) {
    const { data } = await api.post<{ data: Ticket }>(`/support-tickets/${id}/reply`, { message })
    return data.data
  },
}
