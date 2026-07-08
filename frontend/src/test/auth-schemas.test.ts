import { describe, it, expect } from 'vitest'
import { loginSchema, registerSchema } from '@/lib/validation/auth-schemas'

describe('loginSchema', () => {
  it('accepts a valid email and password', () => {
    const result = loginSchema.safeParse({ email: 'a@b.com', password: 'secret' })
    expect(result.success).toBe(true)
  })

  it('rejects an invalid email', () => {
    const result = loginSchema.safeParse({ email: 'not-an-email', password: 'secret' })
    expect(result.success).toBe(false)
  })

  it('rejects an empty password', () => {
    const result = loginSchema.safeParse({ email: 'a@b.com', password: '' })
    expect(result.success).toBe(false)
  })
})

describe('registerSchema', () => {
  const base = {
    name: 'Jane Trader',
    email: 'jane@example.com',
    password: 'StrongPass1!',
    password_confirmation: 'StrongPass1!',
    terms: true as const,
  }

  it('accepts valid registration data', () => {
    expect(registerSchema.safeParse(base).success).toBe(true)
  })

  it('rejects mismatched password confirmation', () => {
    const result = registerSchema.safeParse({ ...base, password_confirmation: 'Different1!' })
    expect(result.success).toBe(false)
  })

  it('rejects a weak password missing a symbol', () => {
    const result = registerSchema.safeParse({
      ...base,
      password: 'WeakPass123',
      password_confirmation: 'WeakPass123',
    })
    expect(result.success).toBe(false)
  })

  it('rejects when terms are not accepted', () => {
    const result = registerSchema.safeParse({ ...base, terms: false as unknown as true })
    expect(result.success).toBe(false)
  })
})
