import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2, Plus, ArrowLeft } from 'lucide-react'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input, Label } from '@/components/ui/input'
import { supportService, type Ticket } from '@/lib/phase5-service'
import { extractApiError } from '@/lib/api'
import { formatDate, cn } from '@/lib/utils'

const statusStyles: Record<string, string> = {
  open: 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
  in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
  resolved: 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
  closed: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
}

function NewTicketForm({ onCreated }: { onCreated: (ticket: Ticket) => void }) {
  const [subject, setSubject] = useState('')
  const [category, setCategory] = useState('other')
  const [message, setMessage] = useState('')
  const [error, setError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: supportService.create,
    onSuccess: (ticket) => onCreated(ticket),
    onError: (err) => setError(extractApiError(err).message),
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle>New ticket</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {error && (
          <div role="alert" className="rounded-lg bg-red-50 dark:bg-red-950/40 text-danger-500 text-sm px-3 py-2">
            {error}
          </div>
        )}
        <div>
          <Label>Subject</Label>
          <Input value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="Brief summary" />
        </div>
        <div>
          <Label>Category</Label>
          <select
            className="flex h-10 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 text-sm"
            value={category}
            onChange={(e) => setCategory(e.target.value)}
          >
            <option value="technical">Technical</option>
            <option value="billing">Billing</option>
            <option value="trading">Trading</option>
            <option value="kyc">KYC</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div>
          <Label>Message</Label>
          <textarea
            className="flex w-full min-h-[100px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm"
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            placeholder="Describe your issue..."
          />
        </div>
        <Button
          isLoading={mutation.isPending}
          disabled={!subject || !message}
          onClick={() => {
            setError(null)
            mutation.mutate({ subject, category, message })
          }}
        >
          <Plus className="h-3.5 w-3.5" /> Submit ticket
        </Button>
      </CardContent>
    </Card>
  )
}

function TicketThread({ ticketId, onBack }: { ticketId: string; onBack: () => void }) {
  const queryClient = useQueryClient()
  const [reply, setReply] = useState('')

  const { data: ticket, isLoading } = useQuery({
    queryKey: ['support-ticket', ticketId],
    queryFn: () => supportService.get(ticketId),
  })

  const replyMutation = useMutation({
    mutationFn: (message: string) => supportService.reply(ticketId, message),
    onSuccess: () => {
      setReply('')
      queryClient.invalidateQueries({ queryKey: ['support-ticket', ticketId] })
    },
  })

  if (isLoading || !ticket) {
    return (
      <div className="flex justify-center py-10">
        <Loader2 className="h-6 w-6 animate-spin text-brand-600" />
      </div>
    )
  }

  const isClosed = ticket.status === 'resolved' || ticket.status === 'closed'

  return (
    <div className="space-y-4">
      <button onClick={onBack} className="flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <ArrowLeft className="h-4 w-4" /> Back to tickets
      </button>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>{ticket.subject}</CardTitle>
          <span className={cn('px-2.5 py-1 rounded-full text-xs font-medium capitalize', statusStyles[ticket.status])}>
            {ticket.status.replace('_', ' ')}
          </span>
        </CardHeader>
        <CardContent className="space-y-4">
          {ticket.messages?.map((m) => (
            <div
              key={m.id}
              className={cn(
                'p-3 rounded-lg text-sm',
                m.author?.role === 'trader'
                  ? 'bg-brand-50 dark:bg-brand-500/10 ml-8'
                  : 'bg-slate-100 dark:bg-slate-800 mr-8',
              )}
            >
              <p className="font-medium text-xs text-slate-500 mb-1">
                {m.author?.name ?? 'You'} · {formatDate(m.created_at)}
              </p>
              <p>{m.message}</p>
            </div>
          ))}

          {!isClosed && (
            <div className="pt-2 space-y-2">
              <textarea
                className="flex w-full min-h-[80px] rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm"
                value={reply}
                onChange={(e) => setReply(e.target.value)}
                placeholder="Type your reply..."
              />
              <Button
                size="sm"
                isLoading={replyMutation.isPending}
                disabled={!reply}
                onClick={() => replyMutation.mutate(reply)}
              >
                Send reply
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

export default function SupportPage() {
  const [view, setView] = useState<'list' | 'new'>('list')
  const [selectedTicketId, setSelectedTicketId] = useState<string | null>(null)
  const queryClient = useQueryClient()

  const { data: tickets, isLoading } = useQuery({ queryKey: ['support-tickets'], queryFn: supportService.list })

  if (selectedTicketId) {
    return <TicketThread ticketId={selectedTicketId} onBack={() => setSelectedTicketId(null)} />
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Support</h1>
          <p className="text-slate-500 mt-1">Get help from our team.</p>
        </div>
        {view === 'list' && <Button onClick={() => setView('new')}>New ticket</Button>}
      </div>

      {view === 'new' ? (
        <NewTicketForm
          onCreated={(ticket) => {
            setView('list')
            queryClient.invalidateQueries({ queryKey: ['support-tickets'] })
            setSelectedTicketId(ticket.id)
          }}
        />
      ) : isLoading ? (
        <div className="flex justify-center py-10">
          <Loader2 className="h-6 w-6 animate-spin text-brand-600" />
        </div>
      ) : tickets && tickets.length > 0 ? (
        <div className="space-y-2">
          {tickets.map((t) => (
            <Card key={t.id} className="cursor-pointer hover:border-brand-300" onClick={() => setSelectedTicketId(t.id)}>
              <CardContent className="py-4 flex items-center justify-between">
                <div>
                  <p className="font-medium">{t.subject}</p>
                  <p className="text-xs text-slate-400 capitalize">{t.category}</p>
                </div>
                <span className={cn('px-2.5 py-1 rounded-full text-xs font-medium capitalize', statusStyles[t.status])}>
                  {t.status.replace('_', ' ')}
                </span>
              </CardContent>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <CardContent className="py-10 text-center text-slate-400">No tickets yet.</CardContent>
        </Card>
      )}
    </div>
  )
}
