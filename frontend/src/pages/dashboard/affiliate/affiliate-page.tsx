import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Copy, Check, Users, TrendingUp, Clock } from 'lucide-react'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { affiliateService } from '@/lib/phase5-service'
import { formatCurrency, formatDate, cn } from '@/lib/utils'

const commissionStatusStyles: Record<string, string> = {
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
  processing: 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
  paid: 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
  failed: 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
}

export default function AffiliatePage() {
  const [copied, setCopied] = useState(false)

  const { data: stats } = useQuery({ queryKey: ['affiliate-stats'], queryFn: affiliateService.stats })
  const { data: referrals } = useQuery({ queryKey: ['affiliate-referrals'], queryFn: affiliateService.referrals })
  const { data: commissions } = useQuery({ queryKey: ['affiliate-commissions'], queryFn: affiliateService.commissions })

  const referralLink = stats ? `${window.location.origin}/register?ref=${stats.referral_code}` : ''

  const handleCopy = async () => {
    await navigator.clipboard.writeText(referralLink)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Affiliate Program</h1>
        <p className="text-slate-500 mt-1">Earn commission when traders you refer purchase a challenge.</p>
      </div>

      {stats && (
        <Card>
          <CardContent className="py-5 flex flex-col sm:flex-row items-center gap-3">
            <code className="flex-1 w-full sm:w-auto bg-slate-100 dark:bg-slate-800 rounded-lg px-4 py-2.5 text-sm truncate">
              {referralLink}
            </code>
            <button
              onClick={handleCopy}
              className="flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:underline shrink-0"
            >
              {copied ? <Check className="h-4 w-4 text-success-500" /> : <Copy className="h-4 w-4" />}
              {copied ? 'Copied!' : 'Copy link'}
            </button>
          </CardContent>
        </Card>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <Card>
          <CardContent className="flex items-center justify-between py-5">
            <div>
              <p className="text-xs text-slate-400">Total Referrals</p>
              <p className="text-xl font-semibold mt-1">{stats?.total_referrals ?? 0}</p>
              <p className="text-xs text-slate-400 mt-0.5">{stats?.converted_referrals ?? 0} converted</p>
            </div>
            <Users className="h-8 w-8 text-brand-500" />
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex items-center justify-between py-5">
            <div>
              <p className="text-xs text-slate-400">Total Earned</p>
              <p className="text-xl font-semibold mt-1">{formatCurrency(stats?.total_earned ?? 0)}</p>
            </div>
            <TrendingUp className="h-8 w-8 text-success-500" />
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex items-center justify-between py-5">
            <div>
              <p className="text-xs text-slate-400">Pending</p>
              <p className="text-xl font-semibold mt-1">{formatCurrency(stats?.total_pending ?? 0)}</p>
            </div>
            <Clock className="h-8 w-8 text-amber-500" />
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Referrals</CardTitle>
        </CardHeader>
        <CardContent>
          {referrals && referrals.length > 0 ? (
            <div className="space-y-2">
              {referrals.map((r, i) => (
                <div key={i} className="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800 last:border-0">
                  <div>
                    <p className="font-medium">{r.name}</p>
                    <p className="text-xs text-slate-400">Joined {formatDate(r.joined_at)}</p>
                  </div>
                  <span className={cn(
                    'px-2 py-0.5 rounded-full text-xs font-medium',
                    r.has_converted ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800',
                  )}>
                    {r.has_converted ? 'Converted' : 'Not yet'}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-slate-400">No referrals yet — share your link to get started.</p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Commission history</CardTitle>
        </CardHeader>
        <CardContent>
          {commissions && commissions.length > 0 ? (
            <div className="space-y-2">
              {commissions.map((c) => (
                <div key={c.id} className="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800 last:border-0">
                  <div>
                    <p className="font-medium">{formatCurrency(c.commission_amount)}</p>
                    <p className="text-xs text-slate-400">
                      {c.commission_pct}% of {formatCurrency(c.order_amount)} from {c.referred_user_name}
                    </p>
                  </div>
                  <span className={cn('px-2.5 py-1 rounded-full text-xs font-medium capitalize', commissionStatusStyles[c.status])}>
                    {c.status}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-slate-400">No commissions yet.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
