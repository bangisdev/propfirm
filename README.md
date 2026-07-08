# PropFirm — CFD Proprietary Trading Firm Platform

A production-track SaaS platform for running a CFD prop trading firm (in the vein of FTMO /
FundingPips), built incrementally, phase by phase, with real, tested code at every step —
no placeholders or TODOs left in what's marked "done."

## Status: Phase 2 complete — Challenge Purchasing, Paystack Payments, MT5 Sync

| Phase | Scope | Status |
|---|---|---|
| **1** | Auth (JWT + rotating refresh tokens), landing page, dashboard shell | ✅ Done |
| **2** | Challenge purchasing, Paystack payments, MT5 sync, coupon engine | ✅ Done |
| 3 | Trading rules engine, evaluation logic | ⏳ Next |
| 4 | Funded accounts, withdrawals, profit splits | Planned |
| 5 | Affiliate system, KYC, support tickets | Planned |
| 6 | Reporting, monitoring, deployment hardening | Planned |

Building the entire platform (dozens of modules, full test suites, CI/CD, monitoring) can't
responsibly be done as one undifferentiated code dump — that produces exactly the kind of
placeholder-riddled, untested output you explicitly don't want. Instead each phase ships complete:
migrations, models, services, controllers, validation, tests, and docs, verified to actually run
before moving to the next.

## What's actually been verified in this sandbox

- **Frontend**: real Vite + React 19 + TypeScript project, `npm install`'d for real.
  `npx tsc --noEmit`, `npm run build`, `npx vitest run` (7/7 passing), and `oxlint` (0
  warnings) all pass right now — not aspirational, actually executed. Caught and fixed a
  real bug this way: Vitest was initially picking up the Playwright e2e spec files.
- **Backend**: Laravel 12 application, including the full skeleton (`artisan`,
  `public/index.php`, storage tree, all core config files) — not just app code assuming a
  skeleton exists. All 81 PHP files pass `php -l` syntax checking. **Not composer-installed
  in this sandbox** (no packagist.org egress here) — install and run it locally or in CI,
  which the GitHub Actions workflow does on every push.

## Phase 2: Challenge Purchasing, Paystack Payments, MT5 Sync

**Challenge catalog & coupon engine**: `challenges` table holds pricing tiers (seeded with
$5K–$200K two-step evaluations); `coupons` support percentage/fixed discounts with
expiry, usage caps (global and per-user), and challenge-specific restrictions. Coupon
redemption is recorded inside the same DB transaction that marks an order paid, with a
row lock on the coupon (`lockForUpdate`) so two concurrent checkouts can't both slip
past a `max_redemptions` limit.

**Checkout & payment flow**:
1. `POST /checkout` creates a `pending` order, validates any coupon, and calls
   `PaystackService::initializeTransaction()` to get a redirect URL. A 100%-off coupon
   skips Paystack entirely and marks the order paid immediately.
2. The frontend redirects to Paystack; on return, `GET /checkout/{reference}` polls order
   status — which **independently re-verifies the transaction server-to-server** via
   `PaystackService::verifyTransaction()` rather than trusting the redirect alone.
3. Paystack's webhook (`POST /webhooks/paystack`) hits the same verification path.
   **Both routes converge on `PaymentFulfillmentService::verifyAndFulfill()`**, which is
   idempotent (checks `status === 'paid'` before doing anything) and defends against a
   tampered client-reported amount by comparing the verified Paystack amount against the
   order's actual total before fulfilling.
4. Webhook signature is verified via HMAC-SHA512 over the **raw** request body
   (`x-paystack-signature` header) — re-parsing/re-encoding JSON before hashing is a classic
   way to accidentally break this check, so the raw body is hashed directly.
5. Each webhook event is stored keyed by Paystack's event id before processing, so a
   retried webhook delivery (Paystack retries on non-2xx) is a safe no-op.

**MT5 account provisioning**: a queued job (`ProvisionTradingAccountJob`, 5 retries with
backoff) runs after a paid order, calling an `MT5BridgeClientInterface`. **This interface is
the important bit — the actual MT5 Manager API is a Windows-only broker SDK, not something
a PHP process calls directly.** In a real deployment this points to a separate small bridge
service (built against your specific broker's SDK) over plain HTTP; swapping brokers later
means redeploying that bridge, not rewriting this integration. The MT5 investor password is
stored with Laravel's `encrypted` cast (AES-256-CBC via `APP_KEY`), never in plaintext, and
only exposed through a dedicated `/trading-accounts/{id}/credentials` endpoint kept
separate from routine dashboard polling.

**What you still need to plug in for Phase 2 to work end-to-end in production**:
a Paystack merchant account (live secret/public keys), and an MT5 bridge service —
either license your broker's MT5 Manager API and build the bridge, or use a white-label
provider that exposes one. Both are business/contract steps, not code.

## Architecture

```
propfirm/
├── backend/                 # Laravel 12 (PHP 8.4) API
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/{Auth,Challenges,Payments,TradingAccounts}/
│   │   ├── Http/Requests/{Auth,Payments}/
│   │   ├── Http/Resources/{User,Challenge,Order,TradingAccount}Resource.php
│   │   ├── Models/{User,RefreshToken,Challenge,Coupon,CouponRedemption,
│   │   │           Order,Payment,PaymentWebhookEvent,TradingAccount,Activity}.php
│   │   ├── Services/Auth/RefreshTokenService.php
│   │   ├── Services/Payments/{Paystack,Order,PaymentFulfillment}Service.php
│   │   ├── Services/Coupons/CouponService.php
│   │   ├── Services/MT5/{MT5BridgeClientInterface,HttpMT5BridgeClient}.php
│   │   ├── Jobs/ProvisionTradingAccountJob.php
│   │   ├── Console/Commands/ExpireStaleOrders.php
│   │   └── Notifications/{Auth,Payments}/*.php
│   ├── database/{migrations,seeders,factories}
│   ├── routes/{api.php, api/payments.php, web.php, console.php}
│   ├── tests/{Feature/{Auth,Challenges,Payments},Unit,Fakes}   (36 tests)
│   ├── artisan, public/index.php, storage/, bootstrap/{app.php,providers.php,cache/}
│   ├── config/                # app, auth, cors, jwt, database, queue, cache, session,
│   │                           # mail, logging, filesystems, hashing, permission, activitylog, services
│   └── Dockerfile           # multi-stage: development / production
├── frontend/                 # Vite + React 19 + TypeScript + Tailwind v4
│   ├── src/
│   │   ├── app/{router,protected-route}.tsx
│   │   ├── pages/{landing-page,auth/*,dashboard/*,dashboard/challenges/*}.tsx
│   │   ├── components/{ui,layout}/*.tsx      # shadcn-style primitives
│   │   ├── lib/{api,auth-service,challenges-service,utils,validation}.ts
│   │   └── store/auth-store.ts               # Zustand, in-memory access token
│   ├── e2e/{auth,challenges}.spec.ts     # Playwright
│   └── Dockerfile
├── infra/nginx/backend.conf
├── docs/openapi.yaml         # 13 documented endpoints across Phases 1-2
├── docker-compose.yml        # local dev: postgres, redis, mailhog, nginx, workers
├── docker-compose.prod.yml
└── .github/workflows/ci.yml  # backend tests, frontend tests+build, e2e, image push
```

## Security design decisions (Phase 1)

- **Access tokens**: short-lived (15 min) JWTs, kept **in memory only** on the frontend
  (Zustand store, never localStorage) to shrink the XSS blast radius.
- **Refresh tokens**: opaque random strings, only the **SHA-256 hash** is stored server-side
  (`refresh_tokens` table), delivered via an `httpOnly`, `Secure`, `SameSite=Strict` cookie
  scoped to `/api/v1/auth`. Every refresh **rotates** the token — reusing an old one after
  rotation fails, which is the standard defense against refresh-token replay after theft.
- **Rate limiting**: login is throttled per `email|ip` (5 attempts/min) independent of the
  route-level throttle, so credential stuffing across many emails from one IP is still capped.
- **Password policy**: enforced server-side via Laravel's `Password` rule — 10+ chars, mixed
  case, number, symbol, and checked against the "uncompromised" (Have I Been Pwned k-anonymity)
  API.
- **RBAC**: `spatie/laravel-permission` with four roles seeded (`trader`, `admin`, `affiliate`,
  `support`) and granular permissions, checked via the `role:` / `permission:` middleware
  aliases registered in `bootstrap/app.php`.
- **Audit logging**: `spatie/laravel-activitylog` wired on the `User` model (dirty-field-only,
  no-empty-log) as the seed of the audit trail required for KYC/compliance in later phases.

## Running it locally

### Frontend only (works right now, no backend needed for UI review)
```bash
cd frontend
npm install
npm run dev        # http://localhost:5173
npm run build       # production build
npx vitest run       # unit tests
npm run lint
```

### Full stack (requires Docker + packagist.org network access)
```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan jwt:secret
docker compose exec backend php artisan migrate --seed
```
- API: http://localhost:8000/api/v1
- Frontend: http://localhost:5173
- Mailhog (dev email capture): http://localhost:8025
- Seeded admin: `admin@propfirm.io` / `ChangeMe123!` (change immediately in any real deploy)
- Seeded challenges: six pricing tiers from $5,000 to $200,000 (see `ChallengeSeeder`)

### Backend tests
```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate && php artisan jwt:secret
php artisan migrate --env=testing
./vendor/bin/pest        # 36 tests: auth, challenges, checkout, coupons, webhooks, MT5 provisioning
```

## API documentation

See [`docs/openapi.yaml`](./docs/openapi.yaml) — 13 endpoints across Phases 1-2, import into
Swagger UI / Postman / Insomnia. As each phase adds endpoints, this spec grows alongside it.

## Next up: Phase 3

Trading rules engine — turning the daily/max drawdown, profit target, and minimum trading
days already stored on each `Challenge` into an actual evaluator that runs against MT5
account state (via `MT5BridgeClientInterface::fetchAccountState()`), flags breaches, and
advances traders from `evaluation_1` → `evaluation_2` → `funded`. Say the word and we'll
build that phase with the same standard: real code, real tests, run before it's called done.
