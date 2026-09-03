# MeroBiz Employee Workspace Update

This update implements a strict separation between portfolio users and staff users.

## Owner / Admin experience

Owner and Admin continue to use the MeroBiz portfolio/business interface appropriate to their assigned businesses. Owner can see combined ownership-adjusted performance. Admin can see assigned business operational/financial data.

## Employee experience

An employee account is a single-company workspace account.

If an employee is assigned to AimersZone, after login the application routes directly to AimersZone. The employee interface is branded with the business name/code and does not show the MeroBiz portfolio, business switcher, ownership, reports, expenses, team management, settings, or any other company.

Employee navigation is limited to:

- Dashboard
- My sales & invoices
- Customers
- Products & services
- New sale

Employee dashboard and invoice endpoints are scoped to invoices created by the logged-in employee. Customer outstanding balances shown to an employee are also calculated only from that employee's invoices, preventing another staff member's sales from being inferred through customer balances.

Product cost and company profit remain hidden from employees.

## Backend enforcement

The restriction is enforced in Laravel as well as Next.js:

- `/api/portfolio/dashboard` returns 403 for employee-only accounts.
- Business middleware rejects access to any business other than the employee's primary active assignment.
- `/api/businesses` returns only the assigned employee business for employee-only accounts.
- Invoice list/detail access is limited to the employee's own invoices.
- Team, expenses, profit reports, ownership and settings remain unavailable through permissions.
- New employee assignments are rejected if the same employee account is already actively assigned to another business.

## Apply to an existing Laragon setup

Extract the patch ZIP into the existing MeroBiz project root and overwrite files.

Then run:

```powershell
cd D:\merobiz-os\merobiz-os\api
php artisan optimize:clear
php artisan migrate
php artisan serve
```

Restart Next.js in another terminal:

```powershell
cd D:\merobiz-os\merobiz-os\web
npm run dev
```

No `migrate:fresh` is required. Existing data is preserved.

The patch also includes the earlier `000150` compatibility migration, so an installation that previously hit the missing `verification_status` migration error can safely run `php artisan migrate` again.
