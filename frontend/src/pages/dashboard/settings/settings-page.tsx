import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2, ShieldCheck, ShieldAlert, Shield } from 'lucide-react'

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/input'
import { kycService } from '@/lib/phase5-service'
import { extractApiError } from '@/lib/api'
import { useAuthStore } from '@/store/auth-store'

const statusConfig = {
  unverified: { icon: Shield, color: 'text-slate-400', label: 'Not verified' },
  pending: { icon: Loader2, color: 'text-amber-500', label: 'Under review' },
  verified: { icon: ShieldCheck, color: 'text-success-500', label: 'Verified' },
  rejected: { icon: ShieldAlert, color: 'text-danger-500', label: 'Rejected' },
} as const

function KycSection() {
  const queryClient = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const [documentType, setDocumentType] = useState('passport')
  const [front, setFront] = useState<File | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data: kyc } = useQuery({ queryKey: ['kyc-status'], queryFn: kycService.status })

  const mutation = useMutation({
    mutationFn: () => kycService.submit(documentType, front!),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kyc-status'] })
      setFront(null)
    },
    onError: (err) => setError(extractApiError(err).message),
  })

  const status = kyc?.kyc_status ?? user?.kyc_status ?? 'unverified'
  const config = statusConfig[status]
  const Icon = config.icon
  const canSubmit = status === 'unverified' || status === 'rejected'

  return (
    <Card>
      <CardHeader>
        <CardTitle>Identity Verification (KYC)</CardTitle>
        <CardDescription>Required before your first payout.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex items-center gap-2">
          <Icon className={`h-5 w-5 ${config.color} ${status === 'pending' ? 'animate-spin' : ''}`} />
          <span className="font-medium">{config.label}</span>
        </div>

        {kyc?.latest_submission?.rejection_reason && (
          <p className="text-sm text-danger-500">Reason: {kyc.latest_submission.rejection_reason}</p>
        )}

        {canSubmit && (
          <div className="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
            {error && (
              <div role="alert" className="rounded-lg bg-red-50 dark:bg-red-950/40 text-danger-500 text-sm px-3 py-2">
                {error}
              </div>
            )}
            <div>
              <Label>Document type</Label>
              <select
                className="flex h-10 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
                value={documentType}
                onChange={(e) => setDocumentType(e.target.value)}
              >
                <option value="passport">Passport</option>
                <option value="national_id">National ID</option>
                <option value="drivers_license">Driver's License</option>
              </select>
            </div>
            <div>
              <Label>Document photo</Label>
              <input
                type="file"
                accept="image/jpeg,image/png,application/pdf"
                onChange={(e) => setFront(e.target.files?.[0] ?? null)}
                className="text-sm"
              />
            </div>
            <Button
              size="sm"
              isLoading={mutation.isPending}
              disabled={!front}
              onClick={() => {
                setError(null)
                mutation.mutate()
              }}
            >
              Submit for verification
            </Button>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

export default function SettingsPage() {
  const user = useAuthStore((s) => s.user)

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Settings</h1>
        <p className="text-slate-500 mt-1">Manage your account and verification.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Profile</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          <p><span className="text-slate-400">Name:</span> {user?.name}</p>
          <p><span className="text-slate-400">Email:</span> {user?.email}</p>
        </CardContent>
      </Card>

      <KycSection />
    </div>
  )
}
