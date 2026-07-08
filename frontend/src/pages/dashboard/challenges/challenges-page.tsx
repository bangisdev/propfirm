import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Check, Loader2 } from 'lucide-react'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { challengesService, checkoutService, type Challenge } from '@/lib/challenges-service'
import { extractApiError } from '@/lib/api'
import { formatCurrency } from '@/lib/utils'

export default function ChallengesPage() {
  const navigate = useNavigate()
  const [selected, setSelected] = useState<Challenge | null>(null)
  const [couponCode, setCouponCode] = useState('')
  const [error, setError] = useState<string | null>(null)

  const { data: challenges, isLoading } = useQuery({
    queryKey: ['challenges'],
    queryFn: challengesService.list,
  })

  const checkoutMutation = useMutation({
    mutationFn: ({ challengeId, coupon }: { challengeId: string; coupon?: string }) =>
      checkoutService.checkout(challengeId, coupon),
    onSuccess: ({ order, authorization_url }) => {
      if (authorization_url) {
        window.location.href = authorization_url
      } else {
        // 100%-off coupon — order is already paid, nothing to redirect to.
        navigate(`/dashboard/challenges/checkout/callback?reference=${order.reference}`)
      }
    },
    onError: (err) => setError(extractApiError(err).message),
  })

  if (isLoading) {
    return (
      <div className="flex justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-brand-600" />
      </div>
    )
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold">Choose your challenge</h1>
        <p className="text-slate-500 mt-1">
          Two-step evaluation. Pass both phases to get funded and start trading our capital.
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {challenges?.map((challenge) => (
          <button
            key={challenge.id}
            onClick={() => setSelected(challenge)}
            className="text-left"
          >
            <Card
              className={
                selected?.id === challenge.id
                  ? 'ring-2 ring-brand-500 border-transparent'
                  : 'hover:border-brand-300 transition-colors'
              }
            >
              <CardHeader>
                <CardTitle className="text-brand-600">
                  {formatCurrency(challenge.account_size)}
                </CardTitle>
                <p className="text-sm text-slate-500">{challenge.name}</p>
              </CardHeader>
              <CardContent className="space-y-2">
                <p className="text-2xl font-bold">{formatCurrency(challenge.price)}</p>
                <ul className="text-sm text-slate-500 space-y-1 mt-3">
                  <li className="flex items-center gap-2">
                    <Check className="h-3.5 w-3.5 text-success-500 shrink-0" />
                    Profit target: {challenge.rules.profit_target_phase1_pct}% / phase
                  </li>
                  <li className="flex items-center gap-2">
                    <Check className="h-3.5 w-3.5 text-success-500 shrink-0" />
                    Max daily drawdown: {challenge.rules.max_daily_drawdown_pct}%
                  </li>
                  <li className="flex items-center gap-2">
                    <Check className="h-3.5 w-3.5 text-success-500 shrink-0" />
                    {challenge.rules.profit_split_pct}% profit split
                  </li>
                </ul>
              </CardContent>
            </Card>
          </button>
        ))}
      </div>

      {selected && (
        <Card className="max-w-md">
          <CardHeader>
            <CardTitle>Checkout — {selected.name}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {error && (
              <div role="alert" className="rounded-lg bg-red-50 dark:bg-red-950/40 text-danger-500 text-sm px-3 py-2">
                {error}
              </div>
            )}
            <div>
              <Input
                placeholder="Coupon code (optional)"
                value={couponCode}
                onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
              />
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-slate-500">Total</span>
              <span className="font-semibold text-lg">{formatCurrency(selected.price)}</span>
            </div>
            <Button
              className="w-full"
              isLoading={checkoutMutation.isPending}
              onClick={() => {
                setError(null)
                checkoutMutation.mutate({ challengeId: selected.id, coupon: couponCode })
              }}
            >
              Proceed to payment
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
