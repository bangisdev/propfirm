import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2, Plus, Wallet2 } from 'lucide-react'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input, Label } from '@/components/ui/input'
import { tradingAccountsService } from '@/lib/challenges-service'
import { payoutsService } from '@/lib/payouts-service'
import { extractApiError } from '@/lib/api'
import { formatCurrency, formatDate, cn } from '@/lib/utils'

const statusStyles: Record<string, string> = {
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
  approved: 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
  processing: 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
  paid: 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
  rejected: 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
  failed: 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
}

function AddBankAccountForm({ onAdded }: { onAdded: () => void }) {
  const [bankName, setBankName] = useState('')
  const [bankCode, setBankCode] = useState('')
  const [accountNumber, setAccountNumber] = useState('')
  const [error, setError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: payoutsService.addBankAccount,
    onSuccess: () => {
      onAdded()
      setBankName('')
      setBankCode('')
      setAccountNumber('')
    },
    onError: (err) => setError(extractApiError(err).message),
  })

  return (
    <div className="space-y-3">
      {error && (
        <div role="alert" className="rounded-lg bg-red-50 dark:bg-red-950/40 text-danger-500 text-sm px-3 py-2">
          {error}
        </div>
      )}
      <div>
        <Label>Bank name</Label>
        <Input value={bankName} onChange={(e) => setBankName(e.target.value)} placeholder="GTBank" />
      </div>
      <div>
        <Label>Bank code</Label>
        <Input value={bankCode} onChange={(e) => setBankCode(e.target.value)} placeholder="058" />
      </div>
      <div>
        <Label>Account number</Label>
        <Input value={accountNumber} onChange={(e) => setAccountNumber(e.target.value)} placeholder="0123456789" />
      </div>
      <Button
        size="sm"
        isLoading={mutation.isPending}
        onClick={() => {
          setError(null)
          mutation.mutate({ bank_name: bankName, bank_code: bankCode, account_number: accountNumber })
        }}
      >
        <Plus className="h-3.5 w-3.5" /> Add bank account
      </Button>
    </div>
  )
}

export default function PayoutsPage() {
  const queryClient = useQueryClient()
  const [showAddBank, setShowAddBank] = useState(false)
  const [requestError, setRequestError] = useState<string | null>(null)
  const [amount, setAmount] = useState('')
  const [selectedAccountId, setSelectedAccountId] = useState('')
  const [selectedBankId, setSelectedBankId] = useState('')

  const { data: accounts } = useQuery({ queryKey: ['trading-accounts'], queryFn: tradingAccountsService.list })
  const { data: bankAccounts } = useQuery({
    queryKey: ['payout-bank-accounts'],
    queryFn: payoutsService.listBankAccounts,
  })
  const { data: payoutHistory, isLoading } = useQuery({
    queryKey: ['payout-requests'],
    queryFn: payoutsService.listRequests,
  })

  const fundedAccounts = accounts?.filter((a) => a.status === 'funded') ?? []

  const requestMutation = useMutation({
    mutationFn: payoutsService.requestPayout,
    onSuccess: () => {
      setAmount('')
      queryClient.invalidateQueries({ queryKey: ['payout-requests'] })
    },
    onError: (err) => setRequestError(extractApiError(err).message),
  })

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Payouts</h1>
        <p className="text-slate-500 mt-1">Request withdrawals from your funded accounts.</p>
      </div>

      {fundedAccounts.length === 0 ? (
        <Card>
          <CardContent className="py-10 text-center text-slate-500">
            <Wallet2 className="h-8 w-8 mx-auto mb-3 text-slate-300" />
            You don't have any funded accounts yet. Pass an evaluation to unlock payouts.
          </CardContent>
        </Card>
      ) : (
        <Card className="max-w-lg">
          <CardHeader>
            <CardTitle>Request a payout</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {requestError && (
              <div role="alert" className="rounded-lg bg-red-50 dark:bg-red-950/40 text-danger-500 text-sm px-3 py-2">
                {requestError}
              </div>
            )}
            <div>
              <Label>Funded account</Label>
              <select
                className="flex h-10 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
                value={selectedAccountId}
                onChange={(e) => setSelectedAccountId(e.target.value)}
              >
                <option value="">Select an account</option>
                {fundedAccounts.map((a) => (
                  <option key={a.id} value={a.id}>
                    {formatCurrency(a.account_size)} — Login {a.mt5_login}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <Label>Bank account</Label>
              {bankAccounts && bankAccounts.length > 0 ? (
                <select
                  className="flex h-10 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
                  value={selectedBankId}
                  onChange={(e) => setSelectedBankId(e.target.value)}
                >
                  <option value="">Select a bank account</option>
                  {bankAccounts.map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.bank_name} •••• {b.masked_account_number.slice(-4)}
                    </option>
                  ))}
                </select>
              ) : (
                <p className="text-sm text-slate-400">No bank accounts yet — add one below.</p>
              )}
            </div>

            <div>
              <Label>Amount</Label>
              <Input
                type="number"
                min="0"
                step="0.01"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                placeholder="500.00"
              />
            </div>

            <Button
              className="w-full"
              isLoading={requestMutation.isPending}
              disabled={!selectedAccountId || !selectedBankId || !amount}
              onClick={() => {
                setRequestError(null)
                requestMutation.mutate({
                  trading_account_id: selectedAccountId,
                  bank_account_id: selectedBankId,
                  amount: parseFloat(amount),
                })
              }}
            >
              Request payout
            </Button>

            <button
              onClick={() => setShowAddBank((v) => !v)}
              className="text-xs text-brand-600 hover:underline"
            >
              {showAddBank ? 'Cancel' : '+ Add a new bank account'}
            </button>
            {showAddBank && (
              <AddBankAccountForm
                onAdded={() => {
                  setShowAddBank(false)
                  queryClient.invalidateQueries({ queryKey: ['payout-bank-accounts'] })
                }}
              />
            )}
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Payout history</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-6">
              <Loader2 className="h-6 w-6 animate-spin text-brand-600" />
            </div>
          ) : payoutHistory && payoutHistory.length > 0 ? (
            <div className="space-y-3">
              {payoutHistory.map((p) => (
                <div
                  key={p.id}
                  className="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-800 last:border-0"
                >
                  <div>
                    <p className="font-medium">
                      {formatCurrency(p.trader_amount)} <span className="text-slate-400 text-sm">of {formatCurrency(p.requested_amount)} requested</span>
                    </p>
                    <p className="text-xs text-slate-400">{formatDate(p.created_at)}</p>
                  </div>
                  <span className={cn('px-2.5 py-1 rounded-full text-xs font-medium capitalize', statusStyles[p.status])}>
                    {p.status}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-slate-400">No payout requests yet.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
