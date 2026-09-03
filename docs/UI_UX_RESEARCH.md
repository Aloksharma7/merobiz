# UI and UX research notes

## Product goal

MeroBiz has two competing requirements:

1. preserve enough financial structure to distinguish sales, business profit, ownership earnings, distributions, and cash received;
2. avoid making ordinary staff interact with a traditional accounting system.

The design therefore follows a **simple surface, rigorous core** approach. Complexity is not deleted; it is placed only where the relevant role and workflow need it.

## Research-informed principles

### 1. Use role-focused information architecture

The interface is not one universal dashboard with every module visible.

- **Portfolio owners** start with combined business performance and personal ownership results.
- **Owners and admins** start with business performance and operational exceptions.
- **Employees** start inside their assigned business with their own sales, collections, customers, catalogue, and the create-sale action.
- **Admins** handle business operations, expenses, payments, staff, reports, and sales visibility; a separate accountant role is intentionally not required.

This reduces scanning and prevents confidential financial data from becoming part of routine sales navigation.

### 2. Keep mobile navigation bounded

The mobile shell uses five primary positions:

- Home
- Sales
- New sale
- Role-specific work area (Expenses for admins; Customers for employees)
- More

The central action is visually distinct but still labelled. Less frequent destinations are in the More sheet rather than forcing a horizontally scrolling menu.

Material Design guidance treats the navigation bar as suitable for a small number of top-level destinations and recommends other patterns when the destination count grows.

References:

- https://m3.material.io/components/navigation-bar/guidelines
- https://m3.material.io/components/navigation-drawer/guidelines

### 3. Use progressive disclosure

Daily screens expose only the fields needed to complete a task:

- invoice: customer, date, item, quantity, price, cost, tax, discount, notes;
- expense: category, payee, date, amount, method, tax, reference, notes;
- customer: identity and contact details;
- product: type, unit, price, cost, tax, optional inventory.

Ownership history, profit closing, partner payouts, and business configuration stay in reports/settings. These controls remain available but do not compete with the primary daily action.

### 4. Make the primary action unmistakable

Each page has one dominant action:

- portfolio → add business;
- sales → new sale;
- expenses → add expense;
- customers → add customer;
- catalogue → add item;
- team → add member;
- reports → close period or record payout, depending on context.

Secondary actions use quieter treatments. Destructive actions are not visually equivalent to normal progress actions.

### 5. Design around financial questions, not database tables

The portfolio header answers the owner’s most important questions:

- What did all businesses sell?
- What profit is attributable to me?
- How much cash was collected?
- How much remains receivable?
- How much closed profit has been paid to me?
- How much is still payable?

The terminology deliberately separates provisional performance from finalized allocations and actual distributions.

### 6. Preserve context when switching businesses

The sidebar and mobile business switcher keep business context visible. Business navigation is nested under the selected company, while the portfolio remains a distinct higher level.

This reduces the risk of adding a transaction to the wrong company and avoids presenting all business records in one undifferentiated list.

### 7. Responsive behavior is structural, not just smaller typography

The interface is designed to reflow at narrow widths:

- desktop tables become transaction cards on mobile;
- sidebars become a bottom bar and sheet;
- metric grids collapse in a readable order;
- modal forms become full-height, scroll-safe mobile surfaces;
- control groups wrap rather than overflow;
- print layout removes application navigation.

WCAG’s reflow guidance uses a 320 CSS-pixel equivalent as an important narrow-width benchmark.

Reference:

- https://www.w3.org/WAI/WCAG22/Understanding/reflow

### 8. Use comfortable interaction targets

The app generally uses controls around 44 pixels high, especially for mobile actions, while smaller icon controls receive enough surrounding hit area.

WCAG 2.2 distinguishes a 24-by-24 CSS-pixel minimum target criterion and a stronger 44-by-44 enhanced criterion. MeroBiz aims for the more comfortable dimension for primary controls even though formal conformance requires a complete audit, not one measurement.

References:

- https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html
- https://www.w3.org/WAI/WCAG22/Understanding/target-size-enhanced.html

### 9. Do not communicate state through colour alone

Statuses appear as text such as `paid`, `partial`, `pending`, and `approved`, with colour as reinforcement. Error messages are written beside their fields, not represented only by a red border.

References:

- https://www.w3.org/WAI/WCAG22/Understanding/error-identification.html
- https://www.w3.org/WAI/WCAG22/Understanding/use-of-color.html

### 10. Keep labels descriptive

Fields have persistent labels. Page headings describe the destination or task. Placeholder text supplements labels rather than replacing them.

Reference:

- https://www.w3.org/WAI/WCAG22/Understanding/headings-and-labels.html

### 11. Manage focus and modal behavior

The custom modal:

- provides a semantic dialog;
- supplies an accessible title and description;
- moves focus into the dialog;
- supports Escape to close where appropriate;
- restores focus to the previously active control;
- prevents background scrolling.

A full accessibility review should additionally verify focus trapping, assistive-technology behavior, contrast, zoom, and keyboard paths on the final deployed browsers.

Reference:

- https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/

### 12. Respect reduced motion

Animation is restrained and CSS honors reduced-motion preferences. Financial comprehension should not depend on animation.

Reference:

- https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions.html

## Visual direction

### Brand character

The visual system was designed to feel like a purpose-built South Asian small-business product without relying on decorative clichés:

- deep evergreen for trust and financial stability;
- saffron accent for primary actions and attention;
- warm off-white surfaces instead of a sterile all-grey dashboard;
- strong numeric typography for financial values;
- rounded but not overly playful containers;
- restrained shadows and fine borders;
- custom empty states, metric treatments, switches, dialogs, and cards.

No Shadcn package is required. The project borrows the general idea of composable primitives but implements its own components and tokens, preventing the result from looking like a default component-library demo.

## Interaction decisions

### Creating a sale

The user stays in one modal:

1. choose customer;
2. choose a catalogue item or use a custom line;
3. confirm price, quantity, cost, discount, and tax;
4. see customer total and estimated gross profit immediately;
5. issue the invoice;
6. open the new invoice and record payment.

Direct cost is visible only to authorized internal users and never appears on the printed customer invoice.

### Recording an expense

Expense entry uses one compact form. Expense approval remains an owner/admin financial-control feature. Sales are different: employee-entered sales are recorded immediately with no approval step.

### Closing profit

Closing is intentionally separated from live dashboards. The modal states that the period will be locked. Allocation is generated from dated ownership records rather than from only the current share percentage.

### Mixed currency

The dashboard warns when businesses use different currencies. It does not produce a misleading combined currency total based on unstored assumptions.

## Accessibility status

The implementation includes accessibility-minded patterns, but it is **not claimed to be formally WCAG certified**. Formal conformance would require automated testing, keyboard and screen-reader testing, zoom/reflow review, colour-contrast measurements, and validation of all final production content.

## Product-system references

- Next.js App Router: https://nextjs.org/docs/app
- Laravel Sanctum SPA authentication: https://laravel.com/docs/13.x/sanctum
- Shopify Polaris design principles and patterns: https://shopify.dev/docs/api/polaris
- Material Design navigation guidance: https://m3.material.io/components/navigation-bar/guidelines
- WCAG 2.2 quick reference: https://www.w3.org/WAI/WCAG22/quickref/
