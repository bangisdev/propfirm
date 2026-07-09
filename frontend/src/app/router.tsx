import { createBrowserRouter } from 'react-router-dom'
import LandingPage from '@/pages/landing-page'
import LoginPage from '@/pages/auth/login-page'
import RegisterPage from '@/pages/auth/register-page'
import DashboardLayout from '@/components/layout/dashboard-layout'
import OverviewPage from '@/pages/dashboard/overview-page'
import ChallengesPage from '@/pages/dashboard/challenges/challenges-page'
import CheckoutCallbackPage from '@/pages/dashboard/challenges/checkout-callback-page'
import WalletPage from '@/pages/dashboard/wallet-page'
import PayoutsPage from '@/pages/dashboard/payouts/payouts-page'
import { ProtectedRoute } from '@/app/protected-route'

export const router = createBrowserRouter([
  { path: '/', element: <LandingPage /> },
  { path: '/login', element: <LoginPage /> },
  { path: '/register', element: <RegisterPage /> },
  {
    element: <ProtectedRoute allowedRoles={['trader']} />,
    children: [
      {
        path: '/dashboard',
        element: <DashboardLayout />,
        children: [
          { index: true, element: <OverviewPage /> },
          { path: 'challenges', element: <ChallengesPage /> },
          { path: 'wallet', element: <WalletPage /> },
          { path: 'payouts', element: <PayoutsPage /> },
        ],
      },
      // Outside the dashboard shell — full-screen confirmation page after Paystack redirect.
      { path: '/dashboard/challenges/checkout/callback', element: <CheckoutCallbackPage /> },
    ],
  },
])
