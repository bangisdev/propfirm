import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { ShieldCheck, Zap, TrendingUp, Globe } from 'lucide-react'

const features = [
  { icon: TrendingUp, title: 'Up to $200,000 funding', desc: 'Trade with our capital after passing a two-step evaluation.' },
  { icon: Zap, title: 'Instant MT5 accounts', desc: 'Get your trading credentials automatically after checkout.' },
  { icon: ShieldCheck, title: 'Transparent rules', desc: 'Clear drawdown, profit target and consistency rules, no surprises.' },
  { icon: Globe, title: '80% profit split', desc: 'Withdraw your profits on a bi-weekly cycle, scaling over time.' },
]

export default function LandingPage() {
  return (
    <div className="bg-white dark:bg-slate-950">
      <header className="border-b border-slate-100 dark:border-slate-800">
        <div className="max-w-7xl mx-auto flex items-center justify-between px-6 h-16">
          <span className="font-bold text-lg text-brand-600">
            PropFirm<span className="text-slate-400 font-normal">.io</span>
          </span>
          <nav className="flex items-center gap-3">
            <Link to="/login">
              <Button variant="ghost">Sign in</Button>
            </Link>
            <Link to="/register">
              <Button>Get Funded</Button>
            </Link>
          </nav>
        </div>
      </header>

      <section className="max-w-5xl mx-auto text-center px-6 py-24">
        <h1 className="text-4xl sm:text-5xl font-bold tracking-tight">
          Get funded up to <span className="text-brand-600">$200,000</span>
        </h1>
        <p className="mt-4 text-lg text-slate-500 max-w-2xl mx-auto">
          Prove your trading skills in our evaluation challenge and trade our capital,
          keeping up to 90% of the profits.
        </p>
        <div className="mt-8 flex items-center justify-center gap-4">
          <Link to="/register">
            <Button size="lg">Start Challenge</Button>
          </Link>
          <Link to="/challenges">
            <Button size="lg" variant="outline">
              View Pricing
            </Button>
          </Link>
        </div>
      </section>

      <section className="max-w-7xl mx-auto px-6 pb-24 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {features.map(({ icon: Icon, title, desc }) => (
          <Card key={title}>
            <CardContent className="py-6">
              <Icon className="h-8 w-8 text-brand-500 mb-3" />
              <h3 className="font-semibold">{title}</h3>
              <p className="text-sm text-slate-500 mt-1">{desc}</p>
            </CardContent>
          </Card>
        ))}
      </section>
    </div>
  )
}
