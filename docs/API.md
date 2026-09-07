# API reference

Base URL in local development:

```text
http://localhost:8000/api
```

All JSON requests should send:

```http
Accept: application/json
Content-Type: application/json
X-Requested-With: XMLHttpRequest
```

The browser client uses Laravel Sanctum stateful SPA authentication. Before login or registration, request:

```http
GET /sanctum/csrf-cookie
```

Then send credentials with cookies enabled.

## Response conventions

Single resources are usually returned as a resource object or inside a named key with a message:

```json
{
  "message": "Invoice created successfully.",
  "invoice": {}
}
```

Validation failures use Laravel’s standard `422` response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "invoice_date": ["The invoice date must be a date before or equal to today."]
  }
}
```

Unauthenticated requests return `401`; business access and permission failures return `403`; missing or out-of-scope nested resources return `404`; domain conflicts such as closed-period changes return a validation/conflict-style JSON message depending on the action.

---

## Authentication

### Register

```http
POST /api/auth/register
```

```json
{
  "name": "Portfolio Owner",
  "email": "owner@example.com",
  "phone": "9800000000",
  "password": "a-strong-password",
  "password_confirmation": "a-strong-password"
}
```

### Login

```http
POST /api/auth/login
```

```json
{
  "email": "owner@example.com",
  "password": "a-strong-password",
  "remember": true
}
```

### Current user

```http
GET /api/auth/me
```

### Logout

```http
POST /api/auth/logout
```

---

## Portfolio

### Portfolio dashboard

```http
GET /api/portfolio/dashboard?start=2026-09-01&end=2026-09-30
```

Returns combined metrics, currency safety metadata, trends, per-business performance, seller activity, closed owner allocation totals, paid distributions, and unpaid allocation balance.

### List accessible businesses

```http
GET /api/businesses
```

### Create business

```http
POST /api/businesses
```

Representative payload:

```json
{
  "name": "Aimers AI",
  "code": "AAI",
  "business_type": "digital_subscription",
  "currency": "NPR",
  "invoice_prefix": "AAI",
  "phone": "9800000000",
  "email": "billing@example.com",
  "address": "Kathmandu, Nepal",
  "default_tax_rate": 0,
  "ownership_percent": 40,
  "profit_share_percent": 40
}
```

The creator becomes the protected primary owner and receives an opening ownership record.

---

# Business-scoped endpoints

All endpoints below use:

```text
/api/businesses/{business}
```

The authenticated user must have an active membership in that business.

## Business

```http
GET   /api/businesses/{business}
PATCH /api/businesses/{business}
GET   /api/businesses/{business}/dashboard?start=...&end=...
```

Currency cannot be changed after financial transactions exist.

## Customers

```http
GET    /api/businesses/{business}/customers?search=&page=1
POST   /api/businesses/{business}/customers
PATCH  /api/businesses/{business}/customers/{customer}
DELETE /api/businesses/{business}/customers/{customer}
```

Create/update example:

```json
{
  "name": "Everest Education Hub",
  "phone": "9800000000",
  "email": "accounts@example.com",
  "address": "Kathmandu",
  "pan_number": "123456789",
  "opening_balance": 0,
  "active": true
}
```

## Products and services

```http
GET    /api/businesses/{business}/products?search=&type=&active=
POST   /api/businesses/{business}/products
PATCH  /api/businesses/{business}/products/{product}
DELETE /api/businesses/{business}/products/{product}
```

Example:

```json
{
  "sku": "AI-MONTH",
  "name": "AI Pro — Monthly",
  "type": "digital_subscription",
  "unit": "account",
  "sale_price": 2400,
  "cost_price": 1600,
  "tax_rate": 0,
  "track_inventory": false,
  "stock_quantity": 0,
  "reorder_level": 0,
  "active": true,
  "metadata": {
    "renewal_days": 30
  }
}
```

Product types:

```text
product | service | digital_subscription
```

## Invoices

```http
GET  /api/businesses/{business}/invoices?search=&status=&page=1
POST /api/businesses/{business}/invoices
GET  /api/businesses/{business}/invoices/{invoice}
POST /api/businesses/{business}/invoices/{invoice}/issue
POST /api/businesses/{business}/invoices/{invoice}/cancel
```

Create example:

```json
{
  "customer_id": 1,
  "invoice_date": "2026-09-01",
  "due_date": "2026-09-08",
  "status": "issued",
  "discount_amount": 100,
  "notes": "Thank you for your business.",
  "items": [
    {
      "product_id": 1,
      "description": "AI Pro — Monthly",
      "quantity": 1,
      "unit_price": 2400,
      "unit_cost": 1600,
      "discount_amount": 0,
      "tax_rate": 0
    }
  ]
}
```

Important behavior:

- invoice number is generated per business/prefix;
- the API recalculates all totals rather than trusting submitted totals;
- future invoice dates are rejected;
- due date cannot precede invoice date;
- stock is checked for tracked physical items;
- current seller commission is snapshotted;
- owner/admin and employee-created sales are recorded immediately;
- employees may create invoices only for catalogue items; product cost and catalogue tax are enforced server-side and internal cost fields are not exposed to the employee;
- closed dates are protected;
- an invoice with a payment cannot be cancelled through the unpaid-cancellation path.

## Payments

```http
POST /api/businesses/{business}/invoices/{invoice}/payments
```

```json
{
  "payment_date": "2026-09-02",
  "amount": 1200,
  "method": "qr",
  "reference": "QR-20260902-001",
  "notes": "First instalment"
}
```

Payment methods:

```text
cash | bank_transfer | qr | wallet | card | cheque | other
```

The date cannot precede the invoice or be in the future, and the amount cannot exceed the open balance.

## Expenses

```http
GET    /api/businesses/{business}/expenses?search=&status=&page=1
POST   /api/businesses/{business}/expenses
PATCH  /api/businesses/{business}/expenses/{expense}/status
DELETE /api/businesses/{business}/expenses/{expense}
```

Create example:

```json
{
  "category": "Software & hosting",
  "vendor": "Cloud Nepal",
  "expense_date": "2026-09-01",
  "amount": 5000,
  "tax_amount": 0,
  "payment_method": "bank_transfer",
  "reference": "EXP-001",
  "notes": "Monthly infrastructure"
}
```

Status update:

```json
{
  "status": "approved"
}
```

Expense statuses:

```text
pending | approved | rejected
```

Only approved expenses affect profit.

## Team

```http
GET   /api/businesses/{business}/team
POST  /api/businesses/{business}/team
PATCH /api/businesses/{business}/team/{membership}
```

Add a member by email:

```json
{
  "name": "Sales Employee",
  "email": "sales@example.com",
  "phone": "9800000000",
  "role": "employee",
  "title": "Sales Executive",
  "commission_rate": 4,
  "joined_at": "2026-09-01"
}
```

Roles:

```text
owner | admin | employee
```

The protected primary owner cannot be deactivated or downgraded through team management.

## Ownership history

```http
GET  /api/businesses/{business}/ownerships
POST /api/businesses/{business}/ownerships
```

```json
{
  "user_id": 1,
  "ownership_percent": 50,
  "profit_share_percent": 50,
  "effective_from": "2026-10-01",
  "notes": "Additional shares acquired"
}
```

The service closes/splits the previous record where needed and validates percentage totals for overlapping dates.

## Profit and loss

```http
GET /api/businesses/{business}/reports/profit-loss?start=2026-09-01&end=2026-09-30
```

Returns sales, cost, gross profit, approved expenses, commissions, net profit, cash collected, receivables, and attributable profit for the signed-in user where applicable.

## Profit periods

```http
GET  /api/businesses/{business}/profit-periods
POST /api/businesses/{business}/profit-periods
```

Close example:

```json
{
  "start_date": "2026-08-01",
  "end_date": "2026-08-31",
  "notes": "August accounts reviewed and closed"
}
```

Closing creates partner allocations and protects that range from ordinary backdated edits.

## Profit distributions

```http
GET  /api/businesses/{business}/profit-distributions
POST /api/businesses/{business}/profit-distributions
```

```json
{
  "profit_allocation_id": 10,
  "distribution_date": "2026-09-05",
  "amount": 15000,
  "method": "bank_transfer",
  "reference": "BANK-TXN-123",
  "notes": "Partial owner payout"
}
```

A distribution cannot exceed the remaining allocation or predate the closed period’s end.
