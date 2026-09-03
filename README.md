# MeroBiz OS

**A simple-to-use multi-business operating system for owners, business admins, partners, and employees.**

MeroBiz keeps daily work uncomplicated while preserving the financial controls needed to calculate trustworthy business profit and ownership-adjusted personal earnings.

It supports different business models—including services, digital subscriptions, and physical products—without hard-coding the application to only three companies.

## What is included

- One portfolio dashboard for all businesses
- Individual business dashboards
- Dated ownership and profit-sharing percentages
- Staff accounts and role-based access
- Customer management
- Product, service, and digital-subscription catalogue
- Professional invoices with multiple line items
- Partial and full payment recording
- Employee sales attribution and commissions with immediate recording
- Server-enforced employee business isolation and hidden internal cost/margin data
- Expense entry, approval, rejection, and audited soft deletion
- Profit-and-loss reporting
- Month/period closing with locked historical figures
- Partner profit allocation and payout tracking
- Print-ready customer invoice page
- Inventory-aware product support
- Audit logs for important changes
- Responsive desktop, tablet, and mobile interface
- Seeded demo portfolio with realistic sample activity

## Financial model

The interface intentionally keeps these figures separate:

```text
Net sales       = invoice subtotal - discounts
Gross profit    = net sales - direct product/service costs
Net profit      = gross profit - approved expenses - employee commissions
Owner profit    = net profit × dated profit-sharing percentage
Amount payable  = closed owner allocation - recorded distributions
Cash received   = distributions actually paid to the owner
```

Historical ownership is date-sensitive. A change from 40% to 50% does not recalculate older closed periods using the new percentage.

## Technology

### Frontend

- Next.js App Router
- React
- TypeScript
- Tailwind CSS
- TanStack Query
- Recharts
- Axios
- Lucide icons
- Sonner notifications

The frontend uses a custom component and visual system rather than a stock UI-library theme.

### Backend

- Laravel API
- Laravel Sanctum cookie/session authentication
- MySQL
- Brick Math for precise decimal calculations
- PHPUnit feature-test structure

### Local infrastructure

- Docker Compose
- MySQL 8.4
- Production-style multi-stage Docker builds

---

# Fastest start: Docker Compose

## Requirements

- Docker Desktop or Docker Engine with Docker Compose
- Free ports `3000`, `8000`, and `3307`

## Start the application

From the project root:

```bash
cp .env.example .env
docker compose up --build
```

Open:

- Web application: `http://localhost:3000`
- API status: `http://localhost:8000`
- API health: `http://localhost:8000/up`
- MySQL forwarded port: `3307`

The API container automatically:

1. waits for MySQL;
2. runs migrations;
3. loads demo records when `SEED_DEMO=true`;
4. starts Laravel on port `8000`.

The local session cookie is intentionally host-only. Leave `SESSION_DOMAIN` empty for `localhost`.

## Demo sign-ins

All demo accounts use the password `password`.

| Account | Email | Intended view |
|---|---|---|
| Portfolio owner | `owner@merobiz.test` | All businesses, ownership, financials, and settings |
| Business admin | `admin@merobiz.test` | Assigned-business overview, operations, staff, sales, and financial controls |
| TechChamp employee | `employee@merobiz.test` | TechChamp Software only; own sales/customers/catalogue workflow |
| Partner | `partner@merobiz.test` | Assigned partner businesses |

The seeded owner has:

- 40% of **Aimers AI**
- 65% of **TechChamp Software**
- 100% of **Enlighten Research**

The demo includes six months of invoices, partial payments, commissions, approved expenses, a closed previous month, owner allocations, and partial distributions.

## Stop or reset

Stop containers:

```bash
docker compose down
```

Delete the demo database and start fresh:

```bash
docker compose down -v
docker compose up --build
```

---

# Manual local development

## 1. Database

Create a MySQL database named `merobiz`, or update the API environment variables to match your database.

## 2. Laravel API

Requirements:

- PHP 8.3 or newer
- Composer 2
- MySQL
- PHP extensions used by Laravel/MySQL, including PDO MySQL, mbstring, XML, cURL, intl, zip, and bcmath

Commands:

```bash
cd api
cp .env.example .env
composer update
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

For a clean database without demo content:

```bash
php artisan migrate
```

## 3. Next.js frontend

Requirements:

- Node.js 22+
- npm

In a second terminal:

```bash
cd web
cp .env.example .env.local
npm install
npm run dev
```

Then open `http://localhost:3000`.

---

# Main user workflows

## Portfolio owner

1. Sign in.
2. See combined sales, estimated attributable profit, cash collected, receivables, paid distributions, and unpaid allocations.
3. Select one business without losing the reporting context.
4. Add or update ownership records from business settings.
5. Review business profit and close a completed period.
6. Record partner payouts against closed allocations.

## Employee

1. Sign in directly to the assigned business workspace.
2. Create invoices and manage customers for that business only.
3. See only personal sales and collections; no portfolio, ownership, team, expenses, settings, or profit reports.
4. See catalogue selling information without internal product cost or margin.
5. Create sales only from the assigned company catalogue; Laravel snapshots the real internal cost and tax.
6. Employee-created sales are recorded immediately and included in the business totals.
7. Record allowed payments and review active catalogue items.

## Business admin

1. See the assigned businesses on the overview dashboard.
2. Review employee sales directly; there is no approval step.
3. Manage expenses, customers, products, payments, and employees.
4. Review business profit-and-loss reports.
5. Close a period after normal sales, payment, and expense checks are complete.

## Invoice lifecycle

```text
Employee sale → recorded immediately → included in business totals
                                   └──→ normal cancellation/refund flow when needed
Invoice → partial payment → paid
Closed period → protected from backdated changes
```

The public-facing invoice omits product costs, commissions, and internal profit information.

---

# Roles and access

| Role | Typical access |
|---|---|
| Owner | Portfolio overview, full business access, ownership, reports, closing, distributions, and team |
| Admin | Assigned-business overview, financials, operations, team, settings, expenses, and all employee sales |
| Employee | Assigned business only: own dashboard, own sales/invoices, customers, catalogue, and permitted payment actions |

Every business-scoped API route passes through membership access control and scoped route binding. Frontend visibility is useful for UX, but backend authorization remains the source of truth.

---

# UX approach

MeroBiz is intentionally not arranged like a large accounting package.

- The owner sees portfolio answers first, then business details.
- Employees receive a company-scoped workflow focused only on their assigned business and daily actions.
- The mobile bar has five predictable destinations and a central sale action.
- Accounting controls such as ownership history, closing, and distributions are placed in reports/settings rather than daily screens.
- Required fields appear first; secondary information is visually quieter.
- Tables become readable transaction cards on narrow screens.
- Status is communicated through text plus styling, never colour alone.
- Destructive and period-closing actions require explicit confirmation.
- Touch controls are designed around approximately 44-pixel targets.
- The design supports reduced-motion preferences and 320-pixel reflow.

The detailed rationale and reference links are in [`docs/UI_UX_RESEARCH.md`](docs/UI_UX_RESEARCH.md).

---

# Project structure

```text
merobiz-os/
├── api/                         Laravel API
│   ├── app/
│   │   ├── Enums/              Roles and financial statuses
│   │   ├── Http/Controllers/   Business-scoped REST endpoints
│   │   ├── Http/Requests/      Validation and input rules
│   │   ├── Http/Resources/     Stable API response shapes
│   │   ├── Models/             Business and financial entities
│   │   ├── Services/           Invoicing, payment, dashboard, closing logic
│   │   └── Support/            Authorization/date/decimal helpers
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/api.php
│   └── tests/Feature/
├── web/                         Next.js application
│   ├── app/                     App Router pages and layouts
│   ├── components/              Custom UI, forms, dashboards, shell
│   ├── lib/                     API, authentication, contexts, types, utilities
│   └── public/
├── docs/
├── docker-compose.yml
└── README.md
```

---

# Environment reference

## Root/Docker variables

| Variable | Local default | Purpose |
|---|---|---|
| `APP_KEY` | local development key | Laravel encryption key; replace in production |
| `DB_DATABASE` | `merobiz` | MySQL database |
| `DB_USERNAME` | `merobiz` | MySQL application user |
| `DB_PASSWORD` | `merobiz` | MySQL application password |
| `DB_ROOT_PASSWORD` | `rootsecret` | MySQL root password |
| `DB_PORT_FORWARD` | `3307` | Host MySQL port |
| `API_ORIGIN` | `http://localhost:8000` | Laravel public origin |
| `FRONTEND_ORIGIN` | `http://localhost:3000` | Allowed frontend origin |
| `NEXT_PUBLIC_API_URL` | `http://localhost:8000/api` | Browser API base URL |
| `NEXT_PUBLIC_API_ORIGIN` | `http://localhost:8000` | Browser CSRF-cookie origin |
| `SESSION_DOMAIN` | empty | Host-only cookie for localhost |
| `SANCTUM_STATEFUL_DOMAINS` | localhost frontend hosts | SPA domains allowed to use stateful auth |
| `SESSION_SECURE_COOKIE` | `false` | Use `true` behind production HTTPS |
| `SEED_DEMO` | `true` | Load demo records after migration |

## Cross-domain deployment

The simplest production topology is a shared parent domain:

```text
app.example.com  → Next.js
api.example.com  → Laravel
```

Typical values:

```dotenv
FRONTEND_ORIGIN=https://app.example.com
API_ORIGIN=https://api.example.com
NEXT_PUBLIC_API_URL=https://api.example.com/api
NEXT_PUBLIC_API_ORIGIN=https://api.example.com
SESSION_DOMAIN=.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com
SESSION_SECURE_COOKIE=true
```

Use HTTPS before enabling secure cookies. Confirm reverse-proxy forwarded headers and CORS at the final public origins.

---

# Reports and accounting boundaries

## Live estimates versus closed figures

Dashboard profit is provisional until a period is closed. A closed period stores:

- net sales;
- cost of sales;
- gross profit;
- approved expenses;
- employee commissions;
- net profit;
- dated partner allocation percentages;
- allocation amounts.

After closing, financial records in that date range are protected from normal backdated changes.

## Currency

Each business has its own base currency. The portfolio dashboard detects mixed currencies and warns instead of silently adding them as though they had already been converted.

Automatic foreign-exchange conversion is deliberately not included in this MVP. A later version should add stored transaction-date exchange rates and an explicit portfolio reporting currency.

## Commission

The current implementation uses a membership commission percentage applied to commissionable invoice sales and stores the resulting amount on the invoice. The data model keeps the calculation historical even when a staff member’s current rate later changes.

---

# API documentation

See [`docs/API.md`](docs/API.md) for the available routes, permissions, payload examples, and response conventions.

# Architecture and integrity rules

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for tenancy, entity relationships, ownership segmentation, period closing, and financial guardrails.

# Deployment

See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) before publishing this project.

# Validation record

See [`docs/VALIDATION.md`](docs/VALIDATION.md) for what was checked in the supplied environment and what still needs to be run after dependencies are installed.

---

# Important production boundaries

This is a serious functional MVP, not a certified accounting or tax filing product.

The following are intentionally outside the current scope:

- Nepal IRD/CBMS-certified electronic billing integration
- automatic PAN/VAT return filing
- automatic bank reconciliation
- licensed payment-gateway or QR-provider integration
- payroll and attendance
- refunds and credit-note workflows beyond the underlying status model
- recurring invoice automation
- purchase orders and full supplier accounting
- automatic exchange-rate conversion
- intercompany elimination in consolidated statements
- file/object storage for receipt uploads
- email/SMS invoice delivery
- offline conflict synchronization

The included invoice is configurable and print-ready, but its legal/tax format must be reviewed for the exact entity, registration status, and current Nepalese requirements before production use.

---

# Production checklist

Before deploying with real financial data:

1. Generate a new `APP_KEY`; never reuse the local example key.
2. Replace all database passwords.
3. Set `APP_DEBUG=false`.
4. Disable demo seeding with `SEED_DEMO=false`.
5. Use HTTPS and secure cookies.
6. Restrict CORS and Sanctum domains to exact production hosts.
7. Configure automated, tested database backups.
8. Add centralized logging and uptime/error monitoring.
9. Run all Laravel tests, frontend type checks, lint, and production builds.
10. Review invoice, tax, retention, privacy, and payment requirements with qualified Nepalese professionals.
11. Add rate limits, MFA, email verification, password reset, and admin security controls appropriate to the risk.
12. Perform a security review before exposing owner-level financial data publicly.

---

# Commands

## API

```bash
cd api
php artisan migrate:fresh --seed
php artisan test
./vendor/bin/pint --test
```

## Web

```bash
cd web
npm run typecheck
npm run lint
npm run build
```

## Docker

```bash
docker compose config
docker compose up --build
docker compose logs -f api web
```

---

## License

MIT. See [`LICENSE`](LICENSE).

## v2.4 — business branding and customer billing

The latest update adds per-business logo/colors/tagline, a fully business-branded staff workspace, one-off customer-name billing without mandatory customer creation, configurable invoice fields, a redesigned A4 customer invoice, and subtle responsive motion. See `BRANDING_INVOICE_UPDATE.md` for the upgrade process.
