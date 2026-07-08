import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useNavigate, useSearchParams, Link } from 'react-router-dom'
import { useState } from 'react'

import { Button } from '@/components/ui/button'
import { Input, Label } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { registerSchema, type RegisterFormValues } from '@/lib/validation/auth-schemas'
import { authService } from '@/lib/auth-service'
import { extractApiError } from '@/lib/api'
import { useAuthStore } from '@/store/auth-store'

export default function RegisterPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const setAccessToken = useAuthStore((s) => s.setAccessToken)
  const setUser = useAuthStore((s) => s.setUser)
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { referral_code: searchParams.get('ref') ?? '' },
  })

  const mutation = useMutation({
    mutationFn: authService.register,
    onSuccess: (data) => {
      setAccessToken(data.access_token)
      setUser(data.user)
      navigate('/dashboard')
    },
    onError: (err) => setServerError(extractApiError(err).message),
  })

  const onSubmit = (values: RegisterFormValues) => {
    setServerError(null)
    mutation.mutate(values)
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-950 px-4 py-10">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Create your account</CardTitle>
          <CardDescription>Start your funded trading journey today</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
            {serverError && (
              <div
                role="alert"
                className="rounded-lg bg-red-50 dark:bg-red-950/40 text-danger-500 text-sm px-3 py-2"
              >
                {serverError}
              </div>
            )}
            <div>
              <Label htmlFor="name">Full name</Label>
              <Input
                id="name"
                autoComplete="name"
                placeholder="John Doe"
                error={errors.name?.message}
                {...register('name')}
              />
            </div>
            <div>
              <Label htmlFor="email">Email address</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                error={errors.email?.message}
                {...register('email')}
              />
            </div>
            <div>
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                autoComplete="new-password"
                placeholder="••••••••"
                error={errors.password?.message}
                {...register('password')}
              />
              <p className="mt-1 text-xs text-slate-400">
                Min 10 characters, with uppercase, lowercase, number & symbol.
              </p>
            </div>
            <div>
              <Label htmlFor="password_confirmation">Confirm password</Label>
              <Input
                id="password_confirmation"
                type="password"
                autoComplete="new-password"
                error={errors.password_confirmation?.message}
                {...register('password_confirmation')}
              />
            </div>
            <div>
              <Label htmlFor="referral_code">Referral code (optional)</Label>
              <Input id="referral_code" {...register('referral_code')} />
            </div>
            <div className="flex items-start gap-2">
              <input
                id="terms"
                type="checkbox"
                className="mt-1 h-4 w-4 rounded border-slate-300"
                {...register('terms')}
              />
              <label htmlFor="terms" className="text-sm text-slate-500">
                I agree to the{' '}
                <Link to="/terms" className="text-brand-600 hover:underline">
                  Terms of Service
                </Link>{' '}
                and{' '}
                <Link to="/privacy" className="text-brand-600 hover:underline">
                  Privacy Policy
                </Link>
              </label>
            </div>
            {errors.terms && <p className="text-xs text-danger-500 -mt-2">{errors.terms.message}</p>}
            <Button type="submit" className="w-full" isLoading={mutation.isPending}>
              Create account
            </Button>
          </form>
          <p className="mt-6 text-center text-sm text-slate-500">
            Already have an account?{' '}
            <Link to="/login" className="text-brand-600 font-medium hover:underline">
              Sign in
            </Link>
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
