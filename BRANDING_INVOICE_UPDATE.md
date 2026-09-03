# Business Branding + Professional Invoice Update

This update completes the staff-branding, direct-customer billing, invoice configuration and UI polish requested for the MeroBiz project.

## What changes

### Staff workspace
- Staff never see the MeroBiz portfolio brand after authentication.
- A staff member is still hard-bound to one assigned business.
- Their workspace uses that business name, logo, tagline and configured colors.
- Their dashboard remains personal: only their sales, invoices, collections, outstanding amounts, commission and recent activity.
- Direct portfolio/other-business access remains blocked by Laravel.

### Per-business branding
Owner/Admin can configure, independently for every business:
- logo upload/removal (PNG, JPG, WebP, max 3 MB)
- tagline
- primary UI color
- navigation/sidebar color
- accent color
- show/hide logo in staff workspace
- show/hide logo on customer invoice

Logos are served by the Laravel API, so `php artisan storage:link` is not required for this feature.

### Faster billing
- Customer name can be typed directly into a sale.
- A saved customer is optional.
- Phone is optional.
- Address/email/PAN are in a collapsed extra-details area.
- A one-off invoice does not create a permanent customer record.
- If a saved customer is selected, its details are snapshotted on the invoice, so later customer edits do not change historical invoices.

### Invoice visibility settings
Core invoice fields remain visible: seller/business name, invoice number/date, customer name, line items and grand total.

Owner/Admin can toggle optional fields per business, including:
- seller address, phone, email, website and PAN
- customer address, phone, email and buyer PAN
- due date, payment mode, prepared-by and status
- item discount and tax columns
- discount and tax summaries
- amount in words
- bank details
- payment history
- notes
- terms
- signature area

Buyer PAN is OFF by default.

### Professional invoice
The print page is redesigned as an A4 customer document with:
- configurable business branding/logo
- seller identity and PAN/VAT information when enabled
- invoice/transaction/issue dates
- payment mode
- customer block
- item table with quantity/rate/discount/tax/amount
- amount in words
- subtotal/discount/tax/grand-total/balance summary
- optional payment/bank information
- terms and authorized-signature area
- print-safe styles for browser Print / Save PDF

The previous `replaceAll` print crash is removed by making `humanize()` null-safe.

### UX polish
- subtle content entrance transitions
- faster button press feedback
- smoother modal opening
- mobile-safe modal heights and footers
- improved input focus feedback
- dynamic business theme variables
- reduced-motion accessibility remains supported

## Upgrade existing Laragon project

1. Stop `php artisan serve` and `npm run dev`.
2. Extract the patch into the existing project root and overwrite files.
3. Run:

```powershell
cd D:\merobiz-os\merobiz-os\api
php artisan optimize:clear
php artisan migrate
php artisan serve
```

4. In another terminal:

```powershell
cd D:\merobiz-os\merobiz-os\web
Remove-Item -Recurse -Force .next -ErrorAction SilentlyContinue
npm run dev
```

No new Composer or npm package was added in this update, so `composer update` and `npm install` are not required when the existing project already runs.

## New migration

`2026_09_02_000300_add_customer_snapshot_to_invoices.php`

This adds optional customer snapshot fields to invoices without deleting existing records and backfills existing invoice customer data.

## Branding setup

Open a business as Owner/Admin:

`Business -> Settings -> Business branding`

Upload the logo, set the tagline/colors and save. Staff assigned to that business inherit the branding automatically.

## Invoice setup

Open:

`Business -> Settings -> Invoice defaults`

Configure contact/tax/bank/signature details and use the visibility toggles to decide which optional fields are printed.
