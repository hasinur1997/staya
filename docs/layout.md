# Staya — Layout

How every Staya screen is put together: the app shell, the navigation, five page templates and
which template each screen uses. Visual values (colours, type, radius, shadows) come from
[`design-system.md`](design-system.md); screens and permissions come from
[`project-overview.md`](project-overview.md). This doc only decides structure.

## Contents

- [Breakpoints and grid](#breakpoints-and-grid)
- [Layer 1 — App shell](#layer-1--app-shell)
- [Layer 2 — Navigation by permission](#layer-2--navigation-by-permission)
- [Layer 3 — Page templates](#layer-3--page-templates)
- [Mobile collapse rules (all templates)](#mobile-collapse-rules-all-templates)
- [Screen → template map](#screen--template-map)
- [Accessibility and keyboard](#accessibility-and-keyboard)
- [Components to build](#components-to-build)
- [Decisions](#decisions)

---

## Breakpoints and grid

| Name    | Width      | Tailwind prefix | Sidebar         | Top bar | Grid columns | Gutter | Side padding |
| ------- | ---------- | --------------- | --------------- | ------- | ------------ | ------ | ------------ |
| Mobile  | < 768px    | (none)          | Drawer, hidden  | 56px    | 4            | 16px   | 16px         |
| Tablet  | 768–1023px | `md:`           | Icon rail, 72px | 64px    | 8            | 24px   | 24px         |
| Desktop | ≥ 1024px   | `lg:`           | Full, 256px     | 64px    | 12           | 24px   | 24px         |

- Breakpoints match Tailwind's defaults (`md` = 768px, `lg` = 1024px), so no custom screens are
  needed. Layout is written mobile-first.
- The content column is `max-w-content` (1200px) and centred in the space to the right of the
  sidebar. Padding sits inside that width.
- Section spacing (vertical gap between page sections): 24px mobile, 32px tablet, 48px desktop.
- Card grid gap: 16px mobile, 24px tablet and desktop.
- Every template places its regions on this grid with `grid-cols-4 md:grid-cols-8 lg:grid-cols-12`.
  Column spans below are written as **desktop / tablet / mobile**, e.g. `8 / 8 / 4`.

---

## Layer 1 — App shell

Used by every signed-in page through `AppLayout`. Sign-in, sign-up and password pages use
`GuestLayout` (see below).

```
Desktop ≥1024px
┌────────────┬───────────────────────────────────────────────────────────┐
│ Logo  Mess │ Breadcrumb › Current          Fund ৳ 12,450.00   🔔  (AV) │ 64px
│            ├───────────────────────────────────────────────────────────┤
│ Dashboard  │      ┌───────────── max 1200px, 24px padding ─────────┐   │
│ Grocery    │      │ Page header                                     │   │
│ Deposits   │      │ Page body (12-column grid)                      │   │
│ Reports    │      │                                                 │   │
│ ─────────  │      └─────────────────────────────────────────────────┘   │
│ Members    │                                                           │
│ Pending (3)│                                                           │
│ ADMIN ▾    │                                                           │
│ Users …    │                                                           │
│   256px    │                                                           │
└────────────┴───────────────────────────────────────────────────────────┘

Tablet 768–1023px                      Mobile <768px
┌────┬──────────────────────────────┐  ┌───────────────────────────────┐
│ ◆  │ Grocery › Purchase  ৳…  🔔 AV│  │ ☰  Grocery purchases   🔔  AV │ 56px
│ ▦  ├──────────────────────────────┤  ├───────────────────────────────┤
│ 🛒 │ Page header                  │  │ Page header                   │
│ ₿  │ Page body (8 columns)        │  │ Page body (4 columns)         │
│ ▤  │                              │  │                               │
│ 72 │                              │  ├───────────────────────────────┤
└────┴──────────────────────────────┘  │ [   Primary action (sticky)  ]│
                                       └───────────────────────────────┘
```

### Sidebar

| Region | Desktop (256px)                                                     | Tablet (72px rail)                                           | Mobile (drawer)                            |
| ------ | ------------------------------------------------------------------- | ------------------------------------------------------------ | ------------------------------------------ |
| Header | Logo + mess name (from settings), links to Dashboard                | Logo mark only                                               | Logo + mess name + close button            |
| Nav    | Icon + label; section labels; "Admin" section collapsible (chevron) | Icon only, label in a tooltip; sections split by a separator | Same as desktop                            |
| Badges | Count badge right-aligned on the item                               | Small count badge over the icon's corner                     | Same as desktop                            |
| Footer | Nothing in v1 (the user menu lives in the top bar)                  | —                                                            | Fund balance (moved here from the top bar) |

- Surface: `bg-background`, 1px right border (`border-border`).
- Active item: 3px terracotta left bar, `bg-primary-soft`, `text-ink`, semibold label,
  `aria-current="page"`. Inactive: `text-ink-muted`, hover `bg-surface-raised`.
- An item is active when the current URL starts with its URL (so `/grocery/12/edit` keeps
  "Grocery purchases" active).
- The tablet rail does not expand. The labelled view is one tap away via the drawer: the top-bar
  menu button is shown on tablet too and opens the same drawer as mobile.
- The mobile drawer is a left `Sheet`, 256px wide (max 85vw), with a dimmed backdrop. It closes on
  backdrop click, Esc, or navigating to a page.
- The desktop collapse toggle (⌘/Ctrl + B in the starter kit) is removed: desktop always shows the
  full sidebar, tablet always shows the rail. One less state to test.

### Top bar

64px tall (56px on mobile), sticky to the top of the content column, `bg-background` with
backdrop blur, gaining a 1px bottom border once the page scrolls (per the design system's nav rule).

| Slot (left → right) | Desktop                                                                       | Tablet                               | Mobile                                                    |
| ------------------- | ----------------------------------------------------------------------------- | ------------------------------------ | --------------------------------------------------------- |
| Menu button         | Hidden                                                                        | Shown (opens drawer)                 | Shown (opens drawer)                                      |
| Breadcrumb          | Full trail                                                                    | Last two levels, earlier ones as "…" | Hidden; replaced by the current page title (`text-label`) |
| Fund balance        | `Fund ৳ 12,450.00`, tabular figures, `text-ink`                               | Same                                 | Hidden here, shown at the bottom of the drawer            |
| Notifications       | Bell icon button with count badge (see open question 1)                       | Same                                 | Same                                                      |
| User menu           | Avatar (32px) → dropdown: name, email, Profile, Security, Appearance, Log out | Same                                 | Same                                                      |

- The fund balance shows only when the server sends it: the user has `ledger.view_balance` and,
  for members, the "members can view shared fund balance" setting is on. Otherwise the slot is
  absent, not empty. It is display only; the value is a server-formatted decimal string.
- Breadcrumbs come from the page (`breadcrumbs` prop), always starting at the section, e.g.
  `Grocery purchases › 14 Oct purchase › Edit`. Dashboard has no breadcrumb, only its title.

### Content area

- `<main id="main-content">` holds the page template, centred at `max-w-content`.
- Vertical padding: 24px top on all sizes; bottom 48px desktop, 24px mobile, plus the height of
  the sticky action bar when one is present (so nothing is hidden behind it).

### GuestLayout

For login, register, forgot/reset password, verify email, two-factor challenge, confirm password,
and the "registration pending / rejected" status screen.

- Full-height `bg-background`, content centred vertically and horizontally.
- Logo + mess name above a single card (`Card`, 12px radius, 16px padding mobile / 32px tablet+).
- Card width: full width minus 16px gutters on mobile; 440px max on tablet and up. The member
  sign-up form is the exception: 640px max, because it has two-column field rows on tablet+.
- Heading (`text-heading`) and one-line description inside the card; footer links ("Back to log in")
  below the card. No sidebar, no top bar, no fund balance.

---

## Layer 2 — Navigation by permission

The server shares the signed-in user's **effective permission names** (Spatie
`getAllPermissionNames()`) as `auth.permissions: string[]`, plus a small `nav` object with
counts. The frontend keeps one nav config where each item declares the permission(s) it needs; an
item renders when the user holds **any** of them. There is no role check anywhere in the nav —
a custom role created in the UI gets exactly the items its permissions allow.

Hiding a nav item is a convenience only. Every route is still guarded by its Policy.

| Section | Item              | Icon (lucide)    | Shown when the user has any of                   | Badge                                                                                                           |
| ------- | ----------------- | ---------------- | ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------- |
| (none)  | Dashboard         | `LayoutGrid`     | — (every signed-in, approved user)               | —                                                                                                               |
| (none)  | Grocery purchases | `ShoppingBasket` | `grocery.view`                                   | —                                                                                                               |
| (none)  | Deposits          | `Wallet`         | `deposit.view_own`, `deposit.view_all`           | —                                                                                                               |
| (none)  | Reports           | `ChartColumn`    | `report.view_own`, `report.view_all`             | —                                                                                                               |
| Manage  | Members           | `Users`          | `member.view`                                    | —                                                                                                               |
| Manage  | Pending approvals | `ClipboardCheck` | `deposit.approve`, `member.approve_registration` | Pending deposits (if `deposit.approve`) + pending registrations (if `member.approve_registration`); hidden at 0 |
| Manage  | Ledger            | `BookOpen`       | `ledger.view_transactions`                       | —                                                                                                               |
| Manage  | Payables          | `HandCoins`      | `ledger.reimburse`                               | Open payables count; hidden at 0                                                                                |
| Admin   | Users             | `UserCog`        | `user.view`                                      | —                                                                                                               |
| Admin   | Roles             | `ShieldCheck`    | `role.view`                                      | —                                                                                                               |
| Admin   | Settings          | `Settings`       | `settings.view`                                  | —                                                                                                               |
| Admin   | Audit log         | `ScrollText`     | `audit.view`                                     | —                                                                                                               |

Rules:

- A section label (and its separator on the rail) renders only if at least one of its items does.
  The first group has no label.
- "Manage" is a label I've added for the managers' group so the sidebar reads in three blocks; the
  items under it are exactly the ones you listed plus the proposed ones.
- The Admin section is collapsible and open by default; its open state is remembered per browser
  (`localStorage`, wrapped in try/catch, falls back to open).
- Counts are computed on the server only for the permissions the user holds, sent as
  `nav.counts.pendingApprovals` / `nav.counts.openPayables`, and are `null` when not applicable.
  Badges use `highlight` (amber) with `text-ink`, pill radius, and include the number in the
  item's accessible name ("Pending approvals, 3 waiting").
- "Settings" in the sidebar is the **mess** settings page. Personal settings (profile, security,
  appearance) stay in the user menu and keep their existing sub-navigation.
- Pending (not yet approved) and rejected applicants never see the shell; they get the
  GuestLayout status screen.
- Inactive members see the same items their permissions allow; create actions on pages are hidden
  via `can.*`, and the server still returns 403.

---

## Layer 3 — Page templates

Every page uses exactly one template. Templates own layout only; pages pass content through
named slots. All spacing, colour and type come from tokens and the shared `components/ui`
primitives (`Card`, `Button`, `Badge`, `Table`, `Sheet`, `DropdownMenu`, …).

### Shared page header

Every template starts with the same header block:

```
[Overline: section / context]                       (optional, text-overline, text-neutral)
Title (text-heading)   [Status badge]               [Secondary] [Primary]
Description or metadata line (text-small, text-ink-muted)
[Tabs or filter bar]                                (template-specific)
```

- One primary button per view, at the right of the header on tablet/desktop.
- Secondary actions: up to two `secondary`/`ghost` buttons; more go into a "More" (`⋯`)
  `DropdownMenu`. Destructive actions (void, reject, reverse) always live in that menu or in a
  dialog, never next to the primary button.
- Mobile: the title wraps, actions move (primary → sticky bottom bar, secondaries → `⋯` menu at the
  top right of the header).

### 1. DashboardTemplate

For a person's home screen: a few headline numbers, then what needs doing.

| Region     | Desktop / Tablet / Mobile span | Contents                                                                                                       |
| ---------- | ------------------------------ | -------------------------------------------------------------------------------------------------------------- |
| Header     | 12 / 8 / 4                     | Greeting title ("Good morning, Rahim"), current month as description, one primary action (e.g. "Add purchase") |
| Stat row   | 3 each / 4 each / 2 each       | 2–4 stat cards (label, money value `text-money`, sub-line). 4 per row desktop, 2 tablet, 2 mobile              |
| Main       | 8 / 8 / 4                      | Primary content: recent purchases, monthly per-member table, review queue preview                              |
| Side panel | 4 / 8 / 4                      | Secondary content: "Fund owes you", recent deposits with status, quick links                                   |

- Tablet: side panel moves below main and spans all 8 columns.
- Mobile: stat cards stay two-up (they hold short values); main then side panel stack below.
- Tables inside follow the list rules (card list on mobile).

### 2. ListTemplate

For a filterable collection of records.

| Region        | Span       | Contents                                                                                                       |
| ------------- | ---------- | -------------------------------------------------------------------------------------------------------------- |
| Header        | 12 / 8 / 4 | Title, record count or period as description, primary "New …" action                                           |
| Filter bar    | 12 / 8 / 4 | Month picker or date range, search, status chips/tabs. Right side: result count                                |
| Summary strip | 12 / 8 / 4 | Optional one-line totals for the filtered set ("Total ৳ 24,000.00 · 38 purchases"), server-computed            |
| Table         | 12 / 8 / 4 | `Table` with sticky header; money right-aligned, tabular; status as `Badge` with its word; row links to detail |
| Pagination    | 12 / 8 / 4 | Previous / next + page info; centred                                                                           |
| Empty state   | 12 / 8 / 4 | Short sentence + the primary action (same button, not a second one)                                            |

- Tablet: the table stays a table; columns marked `priority="low"` are hidden (e.g. "Created by").
- Mobile: the filter bar collapses to one row — month picker + "Filters" button that opens a
  bottom `Sheet` with the rest. The table becomes a **card list** (see collapse rules). The primary
  action becomes the sticky bottom button.

### 3. DetailTemplate

For one record: what it is, its parts, and its history.

| Region     | Span       | Contents                                                                                                                                                                                      |
| ---------- | ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Header     | 12 / 8 / 4 | Overline (record type), title, status badge (e.g. Locked, Voided, Pending), metadata line (created by · date in Asia/Dhaka), actions (primary = the main next step, e.g. "Approve" or "Edit") |
| Main       | 8 / 8 / 4  | Section cards stacked: line items, personal details, deposit history, …                                                                                                                       |
| Side panel | 4 / 8 / 4  | Summary card (totals, buyer, dates), related links, audit history (only with `audit.view`)                                                                                                    |

- Tablet: side panel moves below main. The summary card stays the first block of the side panel,
  so totals come right after the main content.
- Mobile: same stacking; edit-window countdown and Locked badge stay next to the title.
- Section cards may hold their own `Tabs` (member profile: Details / Deposits / Purchases /
  Monthly) — tabs scroll horizontally on mobile, never wrap.

### 4. FormTemplate

For creating or editing one record.

| Region     | Span       | Contents                                                                                                                                                                                   |
| ---------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Header     | 12 / 8 / 4 | Title ("New grocery purchase" / "Edit purchase"), one-line description, no primary button here                                                                                             |
| Form       | 8 / 8 / 4  | Fieldset cards, each with a `text-subhead` legend. Fields stack; two-up rows allowed on tablet+ for short fields (first/last name, date + buyer). Line-item editors are a table on tablet+ |
| Side panel | 4 / 8 / 4  | Optional: live summary (grand total as returned by the server), help text, rules ("You can edit this for 30 minutes")                                                                      |
| Action bar | 8 / 8 / 4  | Under the form: primary "Save …" + secondary "Cancel". Server errors summary sits above it                                                                                                 |

- Tablet: side panel moves **above** the action bar, below the form, so the summary is read just
  before saving.
- Mobile: line-item rows become stacked mini-cards (name, then quantity + unit, then unit price,
  then line total right-aligned); "Add item" is a full-width secondary button. The action bar
  becomes the sticky bottom bar: primary full width; Cancel moves to a `ghost` link in the header.
- Forms that are only one to three fields (submit deposit, reject with reason, void with reason,
  close/reopen month, reimburse) are **dialogs** on the page that triggers them, not FormTemplate
  pages. On mobile those dialogs open as bottom sheets.

### 5. ReportTemplate

For a period summary that people read and print.

| Region      | Span                     | Contents                                                                                                                                                                                                          |
| ----------- | ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Header      | 12 / 8 / 4               | Title ("October 2026"), month status badge (Open / Closed, with "Closed by … on …" in the metadata line), actions: "Print" (secondary), "Close month" or "Reopen month" (primary, only with `report.close_month`) |
| Period bar  | 12 / 8 / 4               | Month/year picker, custom range, member filter                                                                                                                                                                    |
| Summary row | 3 each / 4 each / 2 each | Opening balance, deposits, expenses, closing balance; second row: active members, share per member, adjustments, reimbursements                                                                                   |
| Sections    | 12 / 8 / 4               | Full-width cards in order: per-member table, purchases, pending/rejected deposits, open payables, self-approved deposits, rounding note                                                                           |

- Reports read top to bottom with no side panel, so print order equals screen order.
- Tablet: summary is two-up; tables stay tables with low-priority columns hidden.
- Mobile: period bar stacks (month picker full width, "More filters" sheet); tables become card
  lists; the primary close/reopen action becomes the sticky bottom button; Print moves to `⋯`.
- Print stylesheet: hides sidebar, top bar, period bar, actions and sticky bar; tables never turn
  into cards in print; each section avoids page breaks inside cards.

---

## Mobile collapse rules (all templates)

1. **Columns stack.** Every region spans all 4 columns; order is header → main → side panel →
   actions, unless a template says otherwise.
2. **Side panels go below** the main content (tablet and mobile).
3. **Tables become card lists.** Each row becomes a `Card` that is one link/tap target:
    - Line 1: the record's main label (date or name) left, money right-aligned with tabular figures.
    - Line 2: status `Badge` (word + colour) and up to two secondary facts in `text-small text-ink-muted`.
    - Row actions move into the detail page or a `⋯` menu on the card; never icon-only buttons.
    - Credit shows `+` in `text-success`, due shows `−` in `text-error`, as in tables.
4. **The primary action becomes a sticky bottom button**: a bar fixed to the bottom of the
   viewport, `bg-background` with a top border, 16px padding plus the device safe-area inset,
   holding one large (48px) full-width primary button. The header's primary button is hidden at
   this size so there is still only one. The bar is absent when the user lacks the permission.
5. **Filters fold into a sheet** behind a "Filters" button showing the active-filter count.
6. **Secondary actions move into `⋯`.**
7. Touch targets are at least 40px (44px for nav items and card rows).

---

## Screen → template map

Every screen from the project overview (and the build plan's page lists). "Dialog" means a modal
on the named parent page, not its own route.

| Area              | Screen                                                    | Template / layout            | Notes                                                                      |
| ----------------- | --------------------------------------------------------- | ---------------------------- | -------------------------------------------------------------------------- |
| Auth              | Log in                                                    | GuestLayout                  |                                                                            |
| Auth              | Sign up (full member form + NID upload + password)        | GuestLayout (wide)           | Uses form section rules from FormTemplate inside the guest card            |
| Auth              | Forgot password / reset password                          | GuestLayout                  |                                                                            |
| Auth              | Verify email, two-factor challenge, confirm password      | GuestLayout                  | Existing Fortify pages                                                     |
| Auth              | Registration pending / rejected (with reason)             | GuestLayout                  | Status screen for applicants without access                                |
| Dashboard         | Member dashboard                                          | Dashboard                    | Fund balance, own month summary, fund owes you, recent deposits            |
| Dashboard         | Staff dashboard variant                                   | Dashboard                    | Pending deposits, pending registrations, open payables                     |
| Dashboard         | Inactive member (read-only)                               | Dashboard                    | Same template, no primary action                                           |
| Grocery purchases | Purchase list (month / range filters, voided hidden)      | List                         |                                                                            |
| Grocery purchases | New purchase                                              | Form                         | Line items; server-confirmed totals in side panel                          |
| Grocery purchases | Edit purchase                                             | Form                         | Countdown shown in side panel for owners                                   |
| Grocery purchases | Purchase detail (items, receipt, audit history)           | Detail                       | Locked / Voided badge in header                                            |
| Grocery purchases | Void purchase (reason)                                    | Dialog on Detail             |                                                                            |
| Deposits          | My deposits (status, reasons)                             | List                         | "Submit deposit" is the primary action                                     |
| Deposits          | Submit deposit                                            | Dialog on My deposits        | Amount, date, method                                                       |
| Deposits          | All deposits (`deposit.view_all`)                         | List                         | Same page as My deposits with a "Mine / All" tab when permitted            |
| Deposits          | Record deposit for a member                               | Form                         | Member picker, "approve on save" option                                    |
| Deposits          | Deposit detail (submitter, reviewer, reasons)             | Detail                       |                                                                            |
| Deposits          | Cancel / approve / reject / reverse                       | Dialog on Detail or List row |                                                                            |
| Pending approvals | Approval queue (tabs: Deposits, Registrations)            | List                         | Tabs show only for permissions held                                        |
| Pending approvals | Registration review (applicant details, NID if permitted) | Detail                       | Approve primary; Reject in a dialog                                        |
| Members           | Member list                                               | List                         |                                                                            |
| Members           | New member / edit member                                  | Form                         | Optional "create user account" fieldset                                    |
| Members           | Member profile (details, deposits, purchases, monthly)    | Detail                       | Tabs in main; NID section only with `member.view_nid`                      |
| Members           | Deactivate / reactivate                                   | Dialog on Detail             |                                                                            |
| Ledger            | Transactions (`ledger.view_transactions`)                 | List                         | Summary strip: opening / closing for the filtered period                   |
| Ledger            | Manual adjustment                                         | Form                         | Direction, amount, date, reason                                            |
| Ledger            | Open payables + reimburse                                 | List (+ dialog)              | Reimburse is a dialog on the row                                           |
| Reports           | Monthly report                                            | Report                       | Close / reopen in dialogs                                                  |
| Admin             | Users list                                                | List                         |                                                                            |
| Admin             | New user / edit user (roles, link to member)              | Form                         |                                                                            |
| Admin             | Roles list                                                | List                         |                                                                            |
| Admin             | New role / edit role (permission checklist by resource)   | Form                         | Checklist groups are fieldset cards                                        |
| Admin             | Mess settings                                             | Form                         | Fieldsets: Mess, Fund, Rules, Uploads                                      |
| Admin             | Audit log                                                 | List                         | Filters: date range, actor, action, subject                                |
| Admin             | Audit entry (before/after diff, IP, batch)                | Detail                       |                                                                            |
| Personal          | Profile, security (password, 2FA, passkeys), appearance   | Form                         | Inside the existing settings sub-nav, which sits in the Form's main column |
| Dev               | `/layout-preview`                                         | All five                     | Local only, like `/design-preview`                                         |

---

## Accessibility and keyboard

- A "Skip to content" link is the first focusable element; it jumps to `#main-content`.
- Landmarks: `<nav aria-label="Main">` for the sidebar, `<header>` for the top bar, `<main>`.
- Sidebar: items are links in normal tab order, top to bottom; the Admin toggle is a `button` with
  `aria-expanded`. The current page has `aria-current="page"`.
- Icon rail (tablet): each icon link has an accessible name (its label, plus badge count) and
  shows its tooltip on hover **and** focus.
- Drawer: opened by the menu button (`aria-expanded`, `aria-controls`). Focus moves into the
  drawer on open, is trapped while open, Esc closes it, and focus returns to the menu button.
- The visible focus ring is the global `:focus-visible` terracotta outline; nothing removes it.
- The sticky bottom bar never covers focused fields: forms scroll the focused element above it.
- Status is always a word plus colour; badges never rely on colour alone.

---

## Components to build

| File                                            | Purpose                                                                                 |
| ----------------------------------------------- | --------------------------------------------------------------------------------------- |
| `resources/js/layouts/app-layout.tsx`           | `AppLayout`: shell (sidebar, top bar, main). Replaces the starter-kit shell             |
| `resources/js/layouts/guest-layout.tsx`         | `GuestLayout`: centred card for auth pages                                              |
| `resources/js/components/app-sidebar.tsx`       | Sidebar rendering the filtered nav config; rail and drawer variants                     |
| `resources/js/components/app-top-bar.tsx`       | Breadcrumb, fund balance, notifications, user menu                                      |
| `resources/js/lib/navigation.ts`                | The nav config (items, icons, required permissions) + filter helper                     |
| `resources/js/hooks/use-can.ts`                 | `useCan('member.view')` reading `auth.permissions` — UI mirroring only                  |
| `resources/js/components/page-header.tsx`       | The shared header block                                                                 |
| `resources/js/components/sticky-action-bar.tsx` | Mobile sticky primary action                                                            |
| `resources/js/components/responsive-table.tsx`  | Table on tablet+, card list on mobile, from one column definition                       |
| `resources/js/components/templates/*.tsx`       | `DashboardTemplate`, `ListTemplate`, `DetailTemplate`, `FormTemplate`, `ReportTemplate` |
| `resources/js/pages/layout-preview.tsx`         | Each template with placeholder content (local only)                                     |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares `auth.permissions`, `nav.counts`, `fund.balance` (nullable)                      |

Primitive added to `components/ui`: `table`. A `tabs` primitive is still to come (it needs
`@radix-ui/react-tabs`); add it with the first screen that uses tabs.

---

## Decisions

Approved on 18 Oct 2026.

1. **Notifications.** v1 has no notifications store. The bell is a "Waiting for you" menu built
   from the counts already shared for the sidebar (pending approvals, open payables). It is hidden
   when nothing applies. Members' rejected deposits join it when deposits are built.
2. **Ledger, Payables and Audit log** are in the navigation as listed above.
3. **Mobile side padding** is 16px; tablet and desktop use 24px.
4. **Permissions before Spatie.** `auth.permissions` is shared with its final shape but is empty
   until Phase 1 installs `spatie/laravel-permission`, so real users see only Dashboard for now.
   `/layout-preview?as=member|manager|admin&template=…` (local only) fakes each default role's
   permission set, counts and fund balance for display. It grants nothing.
5. **Permission keys** for users and roles are `user.*` and `role.*` (`user.view`, `role.view`).
6. **Starter-kit shell removed.** `AppLayout` and `GuestLayout` replace the starter kit's sidebar,
   header and auth layout variants. Nav paths for screens not built yet are plain strings in
   `resources/js/lib/navigation.ts`; each switches to its Wayfinder route when the route exists.
