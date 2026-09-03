# Validation record

Final static validation was run on **September 2, 2026**.

## Checks completed on the supplied source

The following checks were run in the build environment after the final source edits:

- PHP syntax lint over all 115 API PHP files
- TypeScript compiler parse pass over all 50 TypeScript/TSX implementation files (plus the generated `next-env.d.ts`) using the locally available compiler mode that does not require installed project packages
- JSON parsing for project manifests
- YAML parsing for Docker Compose
- shell syntax check for the API entrypoint
- scan for unresolved merge markers
- scan for accidental generated dependency directories
- hook-order heuristic to catch React hooks declared after changing early returns
- review of business-scoped routes and permission middleware
- review of dated ownership and closing calculations
- review of future-date, payment-order, payout-order, and currency-change guardrails
- review of local Sanctum/CORS/session-cookie defaults

Feature-test source is included for:

- cross-business isolation and assigned-business listing;
- prevention of employee business creation;
- immediate employee-sale recording with assigned-business isolation;
- server-side protection of internal cost/tax inputs;
- profit calculation;
- protection of closed financial periods.

## Checks that still require dependency installation

The execution environment used to prepare the package could not reach external package registries reliably, so it was not possible to install `vendor/` or `node_modules/` and execute the complete framework runtime.

Run these after downloading:

```bash
cd api
composer install
php artisan test
./vendor/bin/pint --test
```

```bash
cd web
npm install
npm run typecheck
npm run lint
npm run build
```

Then run the full stack:

```bash
docker compose up --build
```

Verify in a browser:

1. owner login;
2. employee login, assigned-business isolation, reduced navigation, and hidden cost/margin data;
3. owner/admin business overview and assigned-business switching;
4. invoice creation and print view;
5. partial and final payments;
6. expense approval;
7. ownership history;
8. profit report;
9. period closing;
10. partner distribution;
11. mobile navigation at approximately 320–430 CSS pixels;
12. keyboard-only modal and form paths;
13. cookie behavior on the final deployment domains.

## Accuracy boundary

Static checks can identify syntax, structure, and many integration problems, but they do not replace a successful dependency-resolved build, database migration, feature-test run, and browser test on the target machine. The package is therefore supplied with transparent validation status rather than an unsupported claim that every runtime path was executed in this environment.
