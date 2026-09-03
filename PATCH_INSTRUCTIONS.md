# Apply this patch to your current MeroBiz project

This patch is intended for the project after the single-business employee workspace and auth hotfix.

## 1. Stop both development servers

Stop `php artisan serve` and `npm run dev` with `Ctrl + C`.

## 2. Extract and overwrite

Extract this ZIP directly over your existing project root:

`D:\merobiz-os\merobiz-os`

Allow Windows to merge folders and replace existing files.

## 3. Laravel

```powershell
cd D:\merobiz-os\merobiz-os\api
php artisan optimize:clear
php artisan migrate
php artisan serve
```

The migration is additive and preserves existing data. Do not run `migrate:fresh`.

No new Composer package is required.

## 4. Next.js

```powershell
cd D:\merobiz-os\merobiz-os\web
Remove-Item -Recurse -Force .next -ErrorAction SilentlyContinue
npm run dev
```

No new npm package is required.

## 5. Configure a business brand

Login as Owner/Admin and go to:

`Business -> Settings -> Business branding`

Set logo, tagline, primary/navigation/accent colors, then save.

## 6. Configure invoice fields

In the same Settings page, use Invoice defaults and the visibility toggles. Buyer PAN is off by default.

## 7. Test staff experience

Login as an Employee assigned to one business. They should see only that business identity and their own sales workspace. No MeroBiz portfolio branding or business switcher should appear.

## 8. Test billing

Create a sale using only a typed customer name. Open the invoice and use `Print / Save PDF`.
