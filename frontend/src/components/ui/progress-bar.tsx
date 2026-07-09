import { cn } from '@/lib/utils'

interface ProgressBarProps {
  label: string
  value: number // 0-100+
  variant?: 'default' | 'danger'
  suffix?: string
}

export function ProgressBar({ label, value, variant = 'default', suffix = '%' }: ProgressBarProps) {
  const clamped = Math.min(100, Math.max(0, value))
  const isDanger = variant === 'danger' && value >= 80

  return (
    <div>
      <div className="flex items-center justify-between text-xs mb-1">
        <span className="text-slate-500">{label}</span>
        <span className={cn('font-medium', isDanger ? 'text-danger-500' : 'text-slate-600 dark:text-slate-300')}>
          {value.toFixed(1)}
          {suffix}
        </span>
      </div>
      <div className="h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
        <div
          className={cn(
            'h-full rounded-full transition-all',
            isDanger ? 'bg-danger-500' : variant === 'danger' ? 'bg-amber-500' : 'bg-brand-500',
          )}
          style={{ width: `${clamped}%` }}
        />
      </div>
    </div>
  )
}
