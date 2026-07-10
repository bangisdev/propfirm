# PropFirm — Production Deployment Guide

This walks you through deploying the platform from a bare server to a fully working
production environment, in the order that avoids the issues people usually hit. Follow
it top to bottom on the first deploy; skip to **§11 Ongoing Deploys** afterward.

---

## 0. What you need before starting

| Requirement | Why |
|---|---|
| A VPS, 2 vCPU / 4GB RAM minimum | Runs ~8 containers (Caddy, backend, nginx, queue worker, scheduler, frontend, postgres, redis) |
| A domain you control | Two subdomains needed: `yourdomain.com` (frontend) and `api.yourdomain.com` (backend) |
| A **live** Paystack account | Test keys won't process real payments. Transfers (for payouts) need separate manual approval from Paystack — request this early, it's not instant |
| Your MT5 bridge service | A separate service you build/license against your broker's MT5 Manager API. Nothing in this repo provides it — see §10 |
| SSH access to the server | Root or sudo access to install Docker |

Any VPS provider works — DigitalOcean, Hetzner, Linode, Vultr are all fine. This guide
assumes Ubuntu 22.04/24.04.

---

## 1. Provision the server

SSH in, then:

```bash
apt-get update && apt-get upgrade -y
curl -fsSL https://get.docker.com | sh
apt-get install -y docker-compose-plugin

# Create a non-root user (recommended — don't run everything as root)
adduser deploy
usermod -aG docker deploy
su - deploy
```

### Firewall
Only open what's needed. Caddy handles 80/443; you don't need to expose Postgres,
Redis, or the backend directly.

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

---

## 2. Point DNS at the server — do this before continuing

Create these **A records**:

| Type | Host | Value |
|---|---|---|
| A | `yourdomain.com` (or `@`) | your server's IP |
| A | `api.yourdomain.com` | your server's IP |
| A | `www.yourdomain.com` (optional) | your server's IP |

DNS propagation can take minutes to hours. Verify before moving on:

```bash
dig +short yourdomain.com
dig +short api.yourdomain.com
```

Both should return your server's IP. If they don't yet, wait — Caddy (§6) will fail to
get a TLS certificate and retry-loop if DNS isn't resolving, which looks like a Caddy
problem but isn't.

---

## 3. Clone the repository

```bash
git clone https://github.com/<you>/propfirm-platform.git
cd propfirm-platform
```

---

## 4. Create both environment files

There are **two separate env files** with two separate purposes. Missing either one
causes a different, confusing class of failure — this is the step most worth being
careful with.

```bash
cp .env.example .env
cp backend/.env.production.example backend/.env.production
```

### `.env` (project root) — read by Docker Compose itself
Used only for `${VARIABLE}` substitution inside `docker-compose.prod.yml` — right now,
just the frontend's build-time API URL:

```
VITE_API_URL=https://api.yourdomain.com/api/v1
```

This gets **compiled into the frontend's JavaScript bundle at build time** (Vite does
this, not at runtime). If you ever change your API domain later, you must rebuild the
frontend image — restarting the container alone won't pick up the change:
```bash
docker compose -f docker-compose.prod.yml up -d --build frontend
```

### `backend/.env.production` — read by Laravel at container runtime
Open it and fill in, at minimum:

```
# Must match each other exactly
DB_PASSWORD=<generate with: openssl rand -base64 32>
POSTGRES_PASSWORD=<same value as DB_PASSWORD>

APP_URL=https://api.yourdomain.com
APP_FRONTEND_URL=https://yourdomain.com

# The single most common "login works but nothing else does" bug: this must be the
# EXACT scheme+domain the frontend is served from, no trailing slash, no wildcard.
# A mismatch causes the browser to silently drop the refresh_token cookie on every
# request — auth will look broken with no obvious error.
CORS_ALLOWED_ORIGINS=https://yourdomain.com

# From your Paystack dashboard → Settings → API Keys & Webhooks. LIVE keys, not test.
PAYSTACK_PUBLIC_KEY=pk_live_xxxxx
PAYSTACK_SECRET_KEY=sk_live_xxxxx

# Your MT5 bridge service (see §10)
MT5_BRIDGE_URL=https://your-mt5-bridge-internal-address
MT5_BRIDGE_API_KEY=<whatever your bridge expects>
```

Leave `APP_KEY` and `JWT_SECRET` **blank** — generated automatically in §7.

---

## 5. Point the Caddyfile at your real domains

```bash
nano infra/Caddyfile
```

Replace the two placeholder blocks (`api.yourdomain.com` and
`yourdomain.com, www.yourdomain.com`) with your actual domains. These must match
`CORS_ALLOWED_ORIGINS` and `VITE_API_URL` from the previous step exactly, or requests
will fail CORS even though every other setting is correct.

---

## 6. Build and start everything

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

This builds the backend and frontend images, then starts all 8 services. Caddy
automatically requests TLS certificates from Let's Encrypt for both domains on first
boot — no certbot, no manual cert management.

Check everything came up:
```bash
docker compose -f docker-compose.prod.yml ps
```
All services should show `running` (or `healthy` for postgres). If `caddy` is
restarting repeatedly, check its logs — almost always a DNS issue, not a Caddy bug:
```bash
docker compose -f docker-compose.prod.yml logs caddy --tail=50
```

---

## 7. One-time application setup

Run these once, right after the containers first come up:

```bash
docker compose -f docker-compose.prod.yml exec backend php artisan key:generate
docker compose -f docker-compose.prod.yml exec backend php artisan jwt:secret
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
docker compose -f docker-compose.prod.yml exec backend php artisan db:seed --force --class=RolesAndPermissionsSeeder
docker compose -f docker-compose.prod.yml exec backend php artisan db:seed --force --class=ChallengeSeeder
```

Deliberately **not** running the full `DatabaseSeeder` — it also creates a demo admin
(`admin@propfirm.io` / `ChangeMe123!`) and, in local/testing environments, fake trader
accounts. Create your real admin instead:

```bash
docker compose -f docker-compose.prod.yml exec backend php artisan tinker
```
```php
$admin = App\Models\User::create([
    'name' => 'Your Name',
    'email' => 'you@yourdomain.com',
    'password' => Hash::make('a-genuinely-strong-password-here'),
    'referral_code' => Str::upper(Str::random(8)),
    'email_verified_at' => now(),
    'kyc_status' => 'verified',
]);
$admin->assignRole('admin');
exit
```

---

## 8. Register the Paystack webhook

In your Paystack dashboard: **Settings → API Keys & Webhooks**, set the webhook URL to:
```
https://api.yourdomain.com/api/v1/webhooks/paystack
```

Without this, initial payments still work (the frontend polls `GET /checkout/{reference}`,
which independently verifies against Paystack directly as a fallback), but payout
transfer completion (`transfer.success`/`transfer.failed`) relies entirely on the webhook
— there's no polling fallback for those.

---

## 9. Verify it's actually working

```bash
curl -I https://api.yourdomain.com/up        # Laravel health check — expect HTTP 200
curl -I https://yourdomain.com                # frontend — expect HTTP 200
```

Then do one real click-through in a browser: register a trader account, confirm the six
seeded challenge tiers show up on the pricing page, and log in as the admin you created
in §7. If the frontend loads but every API call fails (network errors in the browser
console), it's almost always the `CORS_ALLOWED_ORIGINS` / `VITE_API_URL` mismatch — go
back to §4.

---

## 10. The pieces this repo doesn't provide

Two things are business/infrastructure dependencies, not code, and nothing here bypasses them:

- **MT5 bridge service**: the real MT5 Manager API is a Windows-only broker SDK (C/C++/C#),
  not something a Linux PHP process calls directly. `MT5BridgeClientInterface` in the
  backend is the stable HTTP contract this app expects — you need a separate service
  behind `MT5_BRIDGE_URL` that implements `POST /accounts`, `GET /accounts/{login}/state`,
  and `POST /accounts/{login}/disable`, built against your specific broker's SDK (or a
  white-label provider that exposes an equivalent HTTP API).
- **Paystack Transfers approval**: enabling standard payments is instant, but Transfers
  (needed for the payout/withdrawal flow) requires a separate manual review by Paystack.
  Request it as early as possible — it can take days.

---

## 11. Ongoing deploys

```bash
cd propfirm-platform
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
```

The repo's GitHub Actions workflow (`.github/workflows/ci.yml`) already builds and
pushes tagged images to GHCR on every push to `main`. Wiring a deploy step (SSH into
this server and run the two commands above, or switch to pulling the GHCR images
instead of building locally) is the natural next step once manual deploys feel routine.

---

## 12. Backups

At minimum, back up the Postgres volume on a schedule — this holds every user, order,
trading account, and payout record:

```bash
# crontab -e — daily at 3am, keeps 7 days locally
0 3 * * * docker compose -f /path/to/propfirm-platform/docker-compose.prod.yml exec -T postgres pg_dump -U propfirm propfirm | gzip > /backups/propfirm-$(date +\%Y\%m\%d).sql.gz && find /backups -name "propfirm-*.sql.gz" -mtime +7 -delete
```

Store backups somewhere other than the same server (S3, a separate backup host) —
a backup that lives on the machine it's protecting against isn't a backup.

---

## 13. Troubleshooting

| Symptom | Likely cause |
|---|---|
| Login works, everything after silently fails | `CORS_ALLOWED_ORIGINS` doesn't exactly match the frontend's real domain |
| Frontend loads but API calls 404 or hit the wrong host | `VITE_API_URL` was wrong at build time — must rebuild the frontend image, not just restart it |
| Caddy container keeps restarting | DNS not propagated yet, or the Caddyfile still has placeholder domains |
| 500 errors with no detail in the browser | Expected — `APP_DEBUG=false` in production. Check `docker compose -f docker-compose.prod.yml logs backend` |
| Payments succeed but trading accounts never appear | Queue worker isn't running — check `docker compose -f docker-compose.prod.yml ps queue-worker`; provisioning runs on the queue, not inline |
| Payouts never move past "approved" | Paystack Transfers not yet enabled on your account, or the webhook URL isn't registered (§8) |
| `500` on any request right after first deploy | You skipped `php artisan key:generate` or `jwt:secret` in §7 |

When in doubt, check logs in this order: `docker compose -f docker-compose.prod.yml logs backend`,
then `queue-worker`, then `caddy`.

---

## 14. Rolling back

Migrations in this repo are mostly additive (new tables/columns), so the safest rollback
is redeploying the previous git commit rather than running `migrate:rollback` in
production (which can silently drop columns/data you don't want to lose):

```bash
git log --oneline -10          # find the commit to roll back to
git checkout <previous-commit-sha>
docker compose -f docker-compose.prod.yml up -d --build
```

Keep your Postgres backups (§12) current enough that a genuine data-level rollback is
always possible as a last resort.
