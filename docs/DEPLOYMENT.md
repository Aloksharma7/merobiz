# Deployment guide

## Recommended topology

Use separate hosts under one parent domain:

```text
app.example.com → Next.js app
api.example.com → Laravel app
mysql/private    → managed MySQL, kept off the public internet
```

Keep MySQL off the public internet. Route browser traffic only to the frontend and API.

## 1. Prepare secrets

Generate a production Laravel key:

```bash
php artisan key:generate --show
```

Store it in the deployment secret manager as `APP_KEY`.

Use strong unique values for:

- application database password;
- MySQL root/administrative password if self-hosted;
- backup encryption keys;
- mail/payment credentials added later.

Never use the development key or demo passwords from `.env.example` in production.

## 2. Configure origins and cookies

Example:

```dotenv
APP_DEBUG=false
APP_KEY=base64:REPLACE_WITH_GENERATED_KEY

API_ORIGIN=https://api.example.com
FRONTEND_ORIGIN=https://app.example.com
NEXT_PUBLIC_API_URL=https://api.example.com/api
NEXT_PUBLIC_API_ORIGIN=https://api.example.com

SESSION_DOMAIN=.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com
SESSION_SECURE_COOKIE=true
SEED_DEMO=false
```

The frontend origin must be explicitly allowed by Laravel CORS. The frontend host must be present in Sanctum’s stateful domains. Both sites should use HTTPS.

For unrelated top-level domains, cookie and browser policies become more complex. Prefer a shared registrable parent domain.

## 3. Build

The Next.js public API variables are build-time values because they are exposed to browser JavaScript. Rebuild the frontend whenever these values change — see `.github/workflows/deploy-frontend.yml`, which builds `npm run build` on every push and uploads only the finished output; no build step runs on the production server itself.

## 4. Migrate

`.github/workflows/deploy-backend.yml` runs migrations as part of every backend deploy:

```bash
php artisan migrate --force
```

Do not enable `SEED_DEMO` in production.

## 5. Reverse proxy

Configure the proxy/load balancer to:

- terminate TLS;
- redirect HTTP to HTTPS;
- forward `Host`, client IP, protocol, and port headers;
- allow request bodies large enough for future receipt attachments only when needed;
- apply conservative timeouts;
- preserve cookies and CORS headers;
- add security headers after testing the application.

Laravel must trust the proxy chain used by your hosting provider. Configure trusted proxies explicitly for the target platform rather than trusting arbitrary addresses.

## 6. Database

Use MySQL 8-compatible storage with:

- automated daily backups;
- point-in-time recovery where available;
- encryption at rest;
- private networking;
- a least-privilege application user;
- tested restore procedures;
- monitoring for storage, connections, slow queries, and replication health.

A backup is not proven until a restore has been tested.

## 7. Logging and monitoring

At minimum monitor:

- frontend and API availability;
- Laravel exceptions;
- failed authentication and authorization spikes;
- database health;
- app process restarts;
- migration failures;
- disk/storage usage;
- slow API endpoints;
- failed backup jobs.

Forward production logs outside the application container so they survive replacement.

## 8. Security hardening

Before real financial use:

- add email verification;
- add password-reset delivery;
- consider MFA for owners and accountants;
- add active-session/device management;
- tune login and write-endpoint rate limits;
- enforce strong production passwords;
- add Content Security Policy and related browser headers;
- scan dependencies in CI;
- run static analysis and a penetration test;
- establish an incident-response and access-revocation process;
- keep audit logs and backups under separate access controls.

## 9. CI/CD pipeline

Deployment runs automatically through two GitHub Actions workflows, triggered on push to `main`:

- `.github/workflows/deploy-backend.yml` — SSHs into the server, pulls the latest code, runs `composer install`, `php artisan migrate --force`, and refreshes the config/route cache.
- `.github/workflows/deploy-frontend.yml` — builds the Next.js app on GitHub's runners (`npm ci`, `npm run build`), assembles the standalone production bundle, and uploads only that finished output to the server. No `npm`/`node`/build command ever runs on the production server itself; it only receives files.

A useful pre-deploy check sequence, run either locally or as a separate CI job before merging to `main`:

```text
API:
  composer install --no-interaction --prefer-dist
  php artisan test
  vendor/bin/pint --test

Web:
  npm ci
  npm run typecheck
  npm run lint
  npm run build
```

Use lock files after installing dependencies in your development environment and commit them before production deployment.

## 10. Nepal operational review

Before issuing real tax invoices or integrating digital payments, obtain current professional confirmation for:

- PAN/VAT invoice fields and numbering;
- fiscal-year and record-retention treatment;
- cancellation, return, and credit-note rules;
- IRD electronic billing/CBMS applicability;
- payment/QR integration through appropriately licensed providers;
- privacy, employee, and consumer obligations;
- business-specific tax treatment.

The supplied print invoice is a configurable operational document, not evidence of government certification.

## 11. Scaling notes

The current stateless API/web processes can be replicated once sessions/cache/queues are moved to shared infrastructure such as Redis or database-backed stores suitable for the traffic level.

Before horizontal scaling:

- ensure all instances share the same `APP_KEY`;
- share sessions or use a centralized store;
- move user uploads to object storage;
- run scheduled/queued jobs as separate processes;
- avoid running migrations simultaneously from every replica;
- add idempotency around future payment-provider callbacks.
