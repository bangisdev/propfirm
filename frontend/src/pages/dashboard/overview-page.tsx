import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/store/auth-store'
import { TrendingUp, Target, ShieldAlert, CalendarCheck } from 'lucide-react'
import { Link } from 'react-router-dom'

export default function OverviewPage() {
  const user = useAuthStore((s) => s.user)

  const stats = [
    { label: 'Account Balance', value: '$0.00', icon: TrendingUp },
    { label: 'Profit Target', value: '0 / 10%', icon: Target },
    { label: 'Max Drawdown Used', value: '0 / 10%', icon: ShieldAlert },
    { label: 'Trading Days', value: '0 / 5', icon: CalendarCheck },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Welcome back, {user?.name?.split(' ')[0]} 👋</h1>
        <p className="text-slate-500 mt-1">
          Here's an overview of your trading accounts. Purchase a challenge to get started.
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map(({ label, value, icon: Icon }) => (
          <Card key={label}>
            <CardContent className="flex items-center justify-between py-5">
              <div>
                <p className="text-xs text-slate-400">{label}</p>
                <p className="text-xl font-semibold mt-1">{value}</p>
              </div>
              <Icon className="h-8 w-8 text-brand-500" />
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>No active challenges yet</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-slate-500 mb-4">
            Choose an account size and start your evaluation to get funded.
          </p>
          <Link to="/dashboard/challenges">
            <Button>Browse Challenges</Button>
          </Link>
        </CardContent>
      </Card>
    </div>
  )
}
