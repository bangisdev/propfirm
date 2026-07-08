import { api } from '@/lib/api'
import type { AuthUser } from '@/store/auth-store'

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  referral_code?: string
}

interface AuthResponse {
  data: {
    user: AuthUser
    access_token: string
    expires_in: number
  }
}

export const authService = {
  async login(payload: LoginPayload) {
    const { data } = await api.post<AuthResponse>('/auth/login', payload)
    return data.data
  },
  async register(payload: RegisterPayload) {
    const { data } = await api.post<AuthResponse>('/auth/register', payload)
    return data.data
  },
  async me() {
    const { data } = await api.get<{ data: AuthUser }>('/auth/me')
    return data.data
  },
  async logout() {
    await api.post('/auth/logout')
  },
  async forgotPassword(email: string) {
    await api.post('/auth/forgot-password', { email })
  },
  async resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }) {
    await api.post('/auth/reset-password', payload)
  },
}
