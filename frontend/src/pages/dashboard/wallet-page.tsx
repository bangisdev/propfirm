import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Eye, EyeOff, Loader2, Copy, Check } from 'lucide-react'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { tradingAccountsService, type TradingAccount } from '@/lib/challenges-service'
import { formatCurrency, formatDate } from '@/lib/utils'
import { cn } from '@/lib/utils'

const statusStyles: Record<TradingAccount['status'], string> = {
  provisioning: 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
  active: 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
  passed: 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
  funded: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
  failed: 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
  breached: 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
  disabled: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
}

function AccountCredentials({ accountId }: { accountId: string }) {
  const [revealed, setRevealed] = useState(false)
  const [copied, setCopied] = useState(false)

  const mutation = useMutation({
    mutationFn: () => tradingAccountsService.credentials(accountId),
  })

  const handleReveal = () => {
    if (!revealed) mutation.mutate()
    setRevealed((v) => !v)
  }

  const handleCopy = async () => {
    if (mutation.data) {
      await navigator.clipboard.writeText(mutation.data.mt5_password)
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    }
  }

  return (
    <div className="flex items-center gap-2">
      {revealed && mutation.data && (
        <>
          <code className="text-xs bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded">
            {mutation.data.mt5_password}
          </code>
          <button onClick={handleCopy} className="text-slate-400 hover:text-slate-600">
            {copied ? <Check className="h-4 w-4 text-success-500" /> : <Copy className="h-4 w-4" />}
          </button>
        </>
      )}
      {revealed && mutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
      <button
        onClick={handleReveal}
        className="text-xs text-brand-600 hover:underline flex items-center gap-1"
      >
        {revealed ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
        {revealed ? 'Hide' : 'Reveal password'}
      </button>
    </div>
  )
}

export default function WalletPage() {
  const { data: accounts, isLoading } = useQuery({
    queryKey: ['trading-accounts'],
    queryFn: tradingAccountsService.list,
    refetchInterval: (query) => {
      const hasProvisioning = query.state.data?.some((a) => a.status === 'provisioning')
      return hasProvisioning ? 4000 : false
    },
  })

  if (isLoading) {
    return (
      <div className="flex justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-brand-600" />
      </div>
    )
  }

  if (!accounts || accounts.length === 0) {
    return (
      <Card>
        <CardContent className="py-10 text-center">
          <p className="text-slate-500 mb-4">You don't have any trading accounts yet.</p>
          <Link to="/dashboard/challenges">
            <Button>Browse Challenges</Button>
          </Link>
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Wallet & Trading Accounts</h1>
        <p className="text-slate-500 mt-1">Your MT5 evaluation and funded accounts.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {accounts.map((account) => (
          <Card key={account.id}>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle>{formatCurrency(account.account_size)}</CardTitle>
                <p className="text-sm text-slate-500 mt-1">{account.challenge.name}</p>
              </div>
              <span
                className={cn(
                  'px-2.5 py-1 rounded-full text-xs font-medium capitalize',
                  statusStyles[account.status],
                )}
              >
                {account.status}
              </span>
            </CardHeader>
            <CardContent className="space-y-3">
              {account.status === 'provisioning' ? (
                <div className="flex items-center gap-2 text-sm text-slate-500">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Setting up your MT5 account — this usually takes under a minute.
                </div>
              ) : (
                <>
                  <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                      <p className="text-slate-400">MT5 Login</p>
                      <p className="font-medium">{account.mt5_login}</p>
                    </div>
                    <div>
                      <p className="text-slate-400">Server</p>
                      <p className="font-medium">{account.mt5_server}</p>
                    </div>
                    <div>
                      <p className="text-slate-400">Balance</p>
                      <p className="font-medium">
                        {account.current_balance !== null ? formatCurrency(account.current_balance) : '—'}
                      </p>
                    </div>
                    <div>
                      <p className="text-slate-400">Trading Days</p>
                      <p className="font-medium">{account.trading_days_count}</p>
                    </div>
                  </div>
                  <AccountCredentials accountId={account.id} />
                </>
              )}
              {account.breach_reason && (
                <p className="text-xs text-danger-500 mt-2">{account.breach_reason}</p>
              )}
              {account.provisioned_at && (
                <p className="text-xs text-slate-400">
                  Provisioned {formatDate(account.provisioned_at)}
                </p>
              )}
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
