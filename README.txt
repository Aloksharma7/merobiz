MeroBiz migration hotfix

Why this exists:
The immediate-sales patch was applied on a database that did not yet have the
v2 sale compatibility columns. Migration 000200 then failed when it queried
verification_status.

Apply:
1. Extract this ZIP into your existing MeroBiz project root and overwrite/merge.
2. From api/ run:
   php artisan optimize:clear
   php artisan migrate
3. Restart Laravel and Next.js.

This does NOT delete your data and does NOT add an approval step. Employee sales
remain immediate. The compatibility fields are set automatically to verified.
