import { create } from 'zustand'

export type UserRole = 'trader' | 'admin' | 'affiliate' | 'support'

export interface AuthUser {
  id: string
  name: string
  email: string
  role: UserRole
  kyc_status: 'unverified' | 'pending' | 'verified' | 'rejected'
  email_verified_at: string | null
}

interface AuthState {
  user: AuthUser | null
  accessToken: string | null
  isHydrated: boolean
  setAccessToken: (token: string | null) => void
  setUser: (user: AuthUser | null) => void
  setHydrated: (v: boolean) => void
  logout: () => void
}

// Access token is kept in memory only (not localStorage) to reduce XSS blast radius.
// Refresh token lives in an httpOnly, Secure, SameSite=Strict cookie set by the API.
export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  accessToken: null,
  isHydrated: false,
  setAccessToken: (accessToken) => set({ accessToken }),
  setUser: (user) => set({ user }),
  setHydrated: (isHydrated) => set({ isHydrated }),
  logout: () => set({ user: null, accessToken: null }),
}))
