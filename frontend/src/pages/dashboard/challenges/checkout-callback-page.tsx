import { useEffect, useState } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, XCircle, Loader2 } from 'lucide-react'

import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { checkoutService } from '@/lib/challenges-service'

const POLL_INTERVAL_MS = 2500
const MAX_POLLS = 12 // 30 seconds total before giving up and telling the user to check back

export default function CheckoutCallbackPage() {
  const [searchParams] = useSearchParams()
  const reference = searchParams.get('reference') ?? searchParams.get('trxref') ?? ''
  const [pollCount, setPollCount] = useState(0)

  const { data: order, refetch } = useQuery({
    queryKey: ['checkout', reference],
    queryFn: () => checkoutService.pollStatus(reference),
    enabled: !!reference,
    refetchInterval: (query) => {
      const status = query.state.data?.status
      if (status === 'paid' || status === 'failed' || status === 'expired') return false
      return POLL_INTERVAL_MS
    },
  })

  useEffect(() => {
    if (order?.status === 'pending') {
      setPollCount((c) => c + 1)
    }
  }, [order])

  const gaveUp = pollCount >= MAX_POLLS && order?.status === 'pending'

  return (
    <div className="min-h-screen flex items-center justify-center px-4">
      <Card className="w-full max-w-md text-center">
        <CardContent className="py-10">
          {!reference && <p className="text-danger-500">No payment reference found.</p>}

          {reference && !order && (
            <div className="flex flex-col items-center gap-3">
              <Loader2 className="h-10 w-10 animate-spin text-brand-600" />
              <p className="text-slate-500">Confirming your payment…</p>
            </div>
          )}

          {order?.status === 'paid' && (
            <div className="flex flex-col items-center gap-3">
              <CheckCircle2 className="h-14 w-14 text-success-500" />
              <h2 className="text-xl font-bold">Payment successful!</h2>
              <p className="text-slate-500">
                Your MT5 account for {order.challenge.name} is being set up and will appear in
                your dashboard shortly.
              </p>
              <Link to="/dashboard/wallet">
                <Button className="mt-2">Go to Wallet</Button>
              </Link>
            </div>
          )}

          {(order?.status === 'failed' || order?.status === 'expired') && (
            <div className="flex flex-col items-center gap-3">
              <XCircle className="h-14 w-14 text-danger-500" />
              <h2 className="text-xl font-bold">Payment {order.status}</h2>
              <p className="text-slate-500">
                No worries — no charge was completed. You can try again below.
              </p>
              <Link to="/dashboard/challenges">
                <Button variant="outline" className="mt-2">
                  Back to Challenges
                </Button>
              </Link>
            </div>
          )}

          {gaveUp && (
            <div className="flex flex-col items-center gap-3 mt-4">
              <p className="text-sm text-slate-400">
                This is taking longer than expected. If your payment was successful, it'll appear
                in your order history shortly — no need to pay again.
              </p>
              <Button variant="ghost" size="sm" onClick={() => refetch()}>
                Check again
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
