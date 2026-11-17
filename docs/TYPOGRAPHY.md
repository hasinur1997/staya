# Staya — Typography Guidelines

Instructions for fixing typography across the whole Staya app (Laravel + Blade + Tailwind CSS v4 + Vite). Apply them to every page, layout, component, chart and email template. Where this file conflicts with existing styles, this file wins.

---

## 1. Goal

The app currently has no type hierarchy on any screen: headings are oversized but light, labels and values share weights, money amounts don't align, and chart titles compete with the numbers. After this change:

- The money (balances, deposits, expenses) is the most prominent thing on any screen.
- Every piece of text maps to exactly one role from the scale in section 4.
- All money and numbers use tabular figures and line up.

**Scope: English UI only.** Leave Bangla exactly as it is today: its font, its loading, its `:lang(bn)` / locale styles, and its line-heights. Don't add, remove or change anything that only affects Bangla. Bangla typography will be handled separately later.

---

## 2. Fonts

| Role | Family | Weights to load | Where it is used |
| --- | --- | --- | --- |
| UI (English) | **Plus Jakarta Sans** | 400, 500, 600, 700, 800 | All English interface text, numbers, charts |
| Bangla + ৳ sign | **The existing Bangla font — unchanged** | as today | Whatever the app already uses for Bengali script; it also supplies the ৳ glyph |
| Brand only | **Nunito** | 900 | The "Staya" wordmark next to the logo. Nowhere else. |

Rules:
- Replace the current English/Latin UI font with Plus Jakarta Sans. Remove the old Latin UI font (system stack, Figtree, Instrument Sans, Inter, Ubuntu, Nunito outside the wordmark, etc.).
- Do **not** remove or change the existing Bangla font or how it is loaded. First find which font the app currently uses for Bangla (e.g. Hind Siliguri, Noto Sans Bengali, SolaimanLipi) and keep it.
- Never set `font-family` inline or per component. Everything inherits from `--font-sans`, except the wordmark (`font-brand`).
- `--font-sans` = Plus Jakarta Sans first, then the existing Bangla font, then system fallbacks. Plus Jakarta Sans has no Bengali glyphs or ৳, so the browser falls back to the Bangla font for those — Bangla keeps rendering exactly as before.

### Loading

Load from Google Fonts once, in the main layout `<head>` (before the Vite tags), with preconnect:

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito:wght@900&display=swap">
```

Apply this to every layout file (app layout, guest/auth layout, error pages, PDF/print layouts if any). Remove the old Latin UI font `<link>` / `@import`, but keep whatever loads the Bangla font.

---

## 3. Tokens — `resources/css/app.css`

```css
@import 'tailwindcss';

@theme {
  /* Replace 'EXISTING-BANGLA-FONT' with the Bangla font the app already uses */
  --font-sans: 'Plus Jakarta Sans', 'EXISTING-BANGLA-FONT', ui-sans-serif, system-ui, sans-serif;
  --font-brand: 'Nunito', 'Plus Jakarta Sans', sans-serif;

  --text-display: 30px;  --text-display--line-height: 38px;
  --text-heading: 20px;  --text-heading--line-height: 28px;
  --text-title:   16px;  --text-title--line-height:   24px;
  --text-body:    15px;  --text-body--line-height:    22px;
  --text-label:   13px;  --text-label--line-height:   18px;
  --text-caption: 12px;  --text-caption--line-height: 16px;
  --text-money:   26px;  --text-money--line-height:   32px;
  --text-money-lg: 34px; --text-money-lg--line-height: 40px;
  --text-overline: 11px; --text-overline--line-height: 16px;
}

@layer base {
  html {
    font-family: var(--font-sans);
    font-size: 15px;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
  }
  body { @apply text-body text-ink bg-surface; }
  h1, h2, h3, h4 { letter-spacing: -0.02em; }
  input, select, textarea, button { font: inherit; }
}
```

Keep any existing `:lang(bn)` or locale-specific Bangla rules exactly as they are. If an existing Bangla rule sets `font-family`, leave it.

If the project is still on Tailwind v3, put the same values under `theme.extend.fontFamily` and `theme.extend.fontSize` in `tailwind.config.js` instead. Do not keep both setups.

The color utilities used below (`text-ink`, `text-ink-muted`, `text-success`, `text-danger`, `text-info`, `text-primary-text`) come from the Staya design system. If they don't exist yet, add them to `@theme`:

```css
--color-surface: #fbf6ee;  --color-surface-raised: #ffffff;  --color-border: #e6dacb;
--color-ink: #2b211a;      --color-ink-muted: #6e5f52;
--color-primary: #f2a93b;  --color-on-primary: #2b211a;  --color-primary-text: #8f4e0a;
--color-success: #2a7343;  --color-danger: #b42318;  --color-info: #2f5da8;
```

---

## 4. Type scale and roles

Use only these roles. Don't use any other font size or weight anywhere.

| Role | Size / line-height | Weight | Color | Extra | Use for |
| --- | --- | --- | --- | --- | --- |
| `display` | 30 / 38 | 700 | ink | tracking −0.02em | Page H1 ("Welcome, …", "Grocery purchases") — one per page |
| `money-lg` | 34 / 40 | 800 | ink | tabular, tracking −0.02em | The single hero balance on a page (e.g. fund balance on Reports) |
| `heading` | 20 / 28 | 700 | ink | tracking −0.01em | Section headings inside a page |
| `title` | 16 / 24 | 700 | ink | — | Card titles, chart titles, dialog titles, table group headers |
| `money` | 26 / 32 | 700 | ink | tabular, tracking −0.01em | Stat-card values |
| `body` | 15 / 22 | 400 | ink | — | Running text, table cells, form inputs, nav items |
| `body-strong` | 15 / 22 | 600 | ink | — | Active nav item, member names in tables, emphasised cells |
| `label` | 13 / 18 | 600 | ink-muted (ink for form labels) | — | Form labels, buttons, badges, stat-card labels, table column headers |
| `caption` | 12 / 16 | 500 | ink-muted | tabular for numbers | Timestamps, helper text, "from October 2026", edit-window countdown |
| `overline` | 11 / 16 | 700 | ink-muted | uppercase, tracking 0.08em | Sidebar section headers (MANAGE, ADMIN) |
| `wordmark` | 22 / 28 | 900 | ink | Nunito, tracking −0.03em | "Staya" next to the logo — only there |

Button text uses `label` size at weight 600; primary buttons use 15px / 600 when they are the main action of the page.

### Component classes (add to `app.css`)

```css
@layer components {
  .brand-wordmark  { @apply font-brand text-[22px] leading-7 font-black tracking-[-0.03em] text-ink; }

  .page-title      { @apply text-display font-bold tracking-[-0.02em] text-ink; }
  .section-heading { @apply text-heading font-bold tracking-[-0.01em] text-ink; }
  .card-title      { @apply text-title font-bold text-ink; }
  .card-sub        { @apply text-label font-medium text-ink-muted; }

  .stat-label      { @apply text-label font-semibold text-ink-muted; }
  .stat-value      { @apply text-money font-bold tracking-[-0.01em] text-ink tabular-nums whitespace-nowrap; }
  .stat-value-lg   { @apply text-money-lg font-extrabold tracking-[-0.02em] text-ink tabular-nums whitespace-nowrap; }
  .stat-meta       { @apply text-caption font-medium text-ink-muted tabular-nums; }

  .field-label     { @apply text-label font-semibold text-ink; }
  .field-help      { @apply text-caption font-medium text-ink-muted; }
  .field-error     { @apply text-caption font-semibold text-danger; }

  .btn-text        { @apply text-label font-semibold; }

  .nav-item        { @apply text-body font-medium text-ink-muted; }
  .nav-item-active { @apply text-body font-semibold text-ink; }
  .nav-section     { @apply text-overline font-bold uppercase tracking-[0.08em] text-ink-muted; }

  .table-head      { @apply text-label font-semibold text-ink-muted; }
  .table-cell      { @apply text-body text-ink; }

  .badge-text      { @apply text-caption font-semibold; }

  .money           { @apply tabular-nums whitespace-nowrap; }
  .money-credit    { @apply tabular-nums whitespace-nowrap font-semibold text-success; }
  .money-due       { @apply tabular-nums whitespace-nowrap font-semibold text-danger; }
}
```

Use these classes in Blade instead of ad-hoc `text-*` / `font-*` utilities. Spacing and color utilities are still fine alongside them.

---

## 5. Money and numbers

- Format: `৳ 12,450.00` — taka sign, a thin space (`&thinsp;` or U+2009), thousands separators, always two decimals.
- Credit: `+ ৳ 500.00` in `money-credit`. Due: `− ৳ 1,000.00` (real minus sign U+2212, not a hyphen) in `money-due`. Zero: `ink-muted`.
- Every amount, count, date, and percentage uses `tabular-nums`. Money never wraps (`whitespace-nowrap`).
- In tables, right-align money columns (header too).
- Add one Blade helper and use it everywhere instead of formatting by hand:

```php
// app/Support/helpers.php (autoloaded via composer.json "files")
if (! function_exists('taka')) {
    function taka(float|int|string|null $amount, bool $signed = false): string
    {
        $value = (float) ($amount ?? 0);
        $formatted = number_format(abs($value), 2);
        $sign = '';
        if ($signed && $value > 0) $sign = '+ ';
        if ($value < 0) $sign = "\u{2212} ";
        return $sign . "৳\u{2009}" . $formatted;
    }
}
```

```blade
<p class="stat-value">{{ taka($fundBalance) }}</p>
<span class="money-credit">{{ taka($change, signed: true) }}</span>
```

---

## 6. Where to use what — the whole app

This section maps every kind of text in Staya to a role from section 4. It is organised by UI element first (6.1–6.12), because the same element appears on many pages, then by screen (6.13) for anything specific to one page. Every page in the app — not just the dashboard — must follow it.

"Role" means the size/line-height/weight in section 4. Use the matching component class when one exists; otherwise use the role's utilities directly (e.g. role `label` = `text-label font-semibold`).

### 6.1 App shell (every logged-in page)

| Element | Role / class | Notes |
| --- | --- | --- |
| "Staya" next to the logo | `brand-wordmark` | Only place Nunito is used |
| Hostel / mess name under or beside the logo | `caption` (under) or `body-strong` (beside) | Never the same size as the wordmark |
| Sidebar nav item | `nav-item` | Sentence case |
| Sidebar nav item, active | `nav-item-active` | Weight change + active background; no size change |
| Sidebar section header (MANAGE, ADMIN) | `nav-section` (overline) | The only uppercase text in the app |
| Sidebar count badge (Payables "2") | `badge-text` + `tabular-nums` | |
| Collapsible group chevron label | `nav-section` | |
| Top bar page name | `title`, weight 600 | Smaller than the page H1; drop it if the page has an H1 |
| Top bar fund chip: "Fund" | `stat-label` | |
| Top bar fund chip: amount | `body-strong` + `money` | |
| Notification bell count | `badge-text` + `tabular-nums` | |
| Avatar initials | 13px / 700 | |
| Footer / version text | `caption` | |

### 6.2 Page header (top of every page)

| Element | Role / class | Notes |
| --- | --- | --- |
| Page H1 ("Grocery purchases", "Deposits", "Welcome, …") | `page-title` | Exactly one per page |
| Page description under H1 | `body`, ink-muted | One line max |
| Breadcrumbs | `label`, weight 500, ink-muted; current item ink | Separator in ink-muted |
| Page-level primary action ("Add grocery purchase") | 15px / 600 | One primary button per page |
| Secondary page actions (Export, Print) | `btn-text` | |
| Month / period selector label | `field-label` | |
| Month / period selector value | `body` | |

### 6.3 Sections and cards

| Element | Role / class |
| --- | --- |
| Section heading inside a page | `section-heading` |
| Section description | `body`, ink-muted |
| Card title | `card-title` |
| Card subtitle | `card-sub` |
| Card body text | `body` |
| Card footer link ("View all") | `label` in `primary-text` |
| Key–value rows inside a card (e.g. "Purchased by: Hasinur") | key `label` ink-muted, value `body` |

### 6.4 Stat / summary cards (Dashboard, Reports, Ledger, Member profile, Payables)

| Element | Role / class |
| --- | --- |
| Label ("Fund balance now", "Total due") | `stat-label` |
| Value | `stat-value` (`stat-value-lg` only for the single hero number on Reports) |
| Change line ("+ ৳ 11,530.00 from October 2026") | `stat-meta`; the signed amount `money-credit` / `money-due` |
| Secondary meta ("9 meals · 2 members") | `stat-meta` |

### 6.5 Tables and lists (Grocery purchases, Deposits, Members, Meals, Ledger, Payables, Users, Roles)

| Element | Role / class | Notes |
| --- | --- | --- |
| Table title (if inside a card) | `card-title` | |
| Column header | `table-head` | Sentence case, never uppercase |
| Cell text | `table-cell` | |
| Primary column (member name, item name) | `body-strong` | |
| Secondary line inside a cell (phone, email, room no.) | `caption` | Stacked under the primary text |
| Money cell | `table-cell` + `money`, right-aligned | Header right-aligned too |
| Credit / due cell | `money-credit` / `money-due` | Always with + / − sign |
| Date / time cell | `table-cell` + `tabular-nums` | "17 Oct 2026", "9:40 PM" |
| Quantity / meal count cell | `table-cell` + `tabular-nums`, right-aligned | |
| Status cell | badge (6.8) | |
| Row action links (Edit, View) | `label` in `primary-text` | |
| Destructive row action (Delete, Reject) | `label` in `danger` | |
| Totals / footer row | `body-strong` + `money` | Top border, no background |
| Grouped-row header (e.g. a date group in Ledger) | `title` | |
| Mobile card-list version of a table | title `body-strong`, meta `caption`, amount `body-strong money` | |
| Search input text | `body` | |
| Filter chip / month chip | `label` | |
| "Showing 1–10 of 48" | `caption` + `tabular-nums` | |
| Pagination numbers | `label` + `tabular-nums`; current page weight 700 | |

### 6.6 Forms (Add purchase, Add deposit, Member, Meal entry, User, Role, Settings)

| Element | Role / class | Notes |
| --- | --- | --- |
| Form section title | `title` | |
| Form section description | `body`, ink-muted | |
| Field label | `field-label` | Sentence case, no colon |
| Required marker | `field-label` in `danger` | |
| Input / select / textarea text | `body` | Same in date pickers and comboboxes |
| Placeholder | `body`, ink-muted | Example value, never a label substitute |
| Money input | `body` + `tabular-nums`, right-aligned, "৳" prefix as `body` ink-muted | |
| Help text | `field-help` | |
| Validation error | `field-error` | |
| Checkbox / radio / toggle label | `body` | Description under it: `caption` |
| File upload hint ("PNG or JPG, max 2 MB") | `caption` | |
| Line items (grocery items list) — item name / qty / price | `body` / `body` + tabular / `body` + `money` | Subtotal and total: `body-strong money` |
| Locked-entry note ("Locked — editing closed 30 minutes after creation.") | `caption` | |
| Edit-window countdown | `caption` + `tabular-nums` | |
| Submit / cancel buttons | `btn-text` | |

### 6.7 Buttons and links

| Element | Role |
| --- | --- |
| Page primary button | 15px / 600 |
| All other buttons (secondary, ghost, danger, in dialogs, in tables) | `btn-text` (13px / 600) |
| Icon-only buttons | No text; `aria-label` instead |
| Inline link in running text | inherits size, weight 600, `primary-text` |
| Standalone link ("View all", "Forgot password?") | `label` in `primary-text` |

### 6.8 Badges, status and tags

| Element | Role | Notes |
| --- | --- | --- |
| Status badge (Approved, Pending, Rejected, Locked, Voided, Paid, Due) | `badge-text` | Always the word, never colour alone |
| Role tag (Manager, Member, Admin) | `badge-text` | |
| Count badge | `badge-text` + `tabular-nums` | |

### 6.9 Dialogs, drawers and confirmations

| Element | Role |
| --- | --- |
| Dialog / drawer title | `card-title` |
| Dialog body | `body` |
| Confirmation question ("Reject this deposit?") | `title` |
| Consequence line ("The member will be notified.") | `body`, ink-muted |
| Amount being confirmed | `stat-value` |
| Buttons | `btn-text` |

### 6.10 Feedback: toasts, alerts, empty and error states

| Element | Role |
| --- | --- |
| Toast message | `body`, weight 500 |
| Toast secondary line / undo link | `caption` / `label` in `primary-text` |
| Inline alert / banner title | `body-strong` |
| Inline alert / banner text | `body` |
| Empty state title ("No deposits this month") | `card-title` |
| Empty state text | `body`, ink-muted |
| Loading text | `caption` |
| Error page code (404, 500) | `page-title` |
| Error page message | `body` |

### 6.11 Menus, tabs, popovers, tooltips

| Element | Role |
| --- | --- |
| Dropdown / user-menu item | `body` |
| Dropdown section label | `nav-section` |
| Dropdown item description | `caption` |
| Notification item title | `body-strong` |
| Notification item text | `body` |
| Notification time | `caption` |
| Tabs | `label`; active tab weight 700 + `primary-text` underline |
| Segmented control | `label` |
| Tooltip | `caption` |
| Popover title / text | `title` / `body` |
| Keyboard shortcut hint | `caption`, `tabular-nums` |

### 6.12 Charts (anywhere)

| Element | Role |
| --- | --- |
| Chart title / subtitle (in card HTML) | `card-title` / `card-sub` |
| Legend | `caption` |
| Axis labels and ticks | 12px / 500 ink-muted, tabular |
| Tooltip title | `label` |
| Tooltip value | `body-strong` + `money` |
| Data labels on bars/points | `caption` + tabular |

### 6.13 Screen-specific notes

| Screen | What is specific |
| --- | --- |
| **Auth (login, register, forgot/reset password, verify email)** | Logo + `brand-wordmark` centred; form title `heading`; intro `body` ink-muted; then 6.6. |
| **Dashboard** | H1 `page-title`; stat cards 6.4; charts 6.12; "Recent activity" list: name `body-strong`, action `body`, time `caption`. |
| **Grocery purchases** | List 6.5 (date, purchased by, items count, total). Purchase detail: title `heading`, line items 6.6, total `stat-value`, receipt image caption `caption`. |
| **Deposits** | List 6.5 with status badges; approve/reject dialogs 6.9; deposit amount in detail view `stat-value`; reference/transaction ID `caption` + `tabular-nums`. |
| **Reports** | Report title `page-title`; period line `body` ink-muted; hero closing balance `stat-value-lg`; summary cards 6.4; member-wise table 6.5 with credit/due cells; print layout below. |
| **Members** | List 6.5 (name `body-strong`, phone/room `caption`, balance `money-credit`/`money-due`). Member profile: name `heading`, meta `caption`, balance `stat-value`. |
| **Meals** | Daily grid/calendar: date header `table-head`, meal counts `table-cell` + `tabular-nums` centred; daily total row `body-strong`; month total `stat-value`. |
| **Ledger** | Running list: date group `title`; entry description `body`; amount `money-credit`/`money-due`; running balance `body-strong money`, right-aligned. |
| **Payables** | Who owes whom: name `body-strong`; amount `money-due`; due date `caption`; "Mark as paid" `btn-text`. |
| **Users** | List 6.5 (name `body-strong`, email `caption`, role badge 6.8, last login `caption`). |
| **Roles** | Role name `title`; permission group `nav-section`; permission label `body`; permission description `caption`. |
| **Settings** | Left settings menu `nav-item`/`nav-item-active`; each panel title `heading`; description `body` ink-muted; forms 6.6; danger zone title `title` in `danger`. |
| **Profile** | Name `heading`; fields 6.6. |
| **Error pages** | See 6.10. |
| **Print / PDF reports** | Same fonts; title 24pt/700, section 14pt/700, body and table 11pt (min), money tabular and right-aligned; no shadows or tinted fills. |
| **Emails (notifications, password reset)** | Plus Jakarta Sans with `Arial, sans-serif` fallback; heading 22px/700, body 15px/22px, button 15px/600, footer 12px muted. |

---

## 7. Charts

Set fonts globally once, wherever charts are initialised:

```js
// Chart.js
Chart.defaults.font = { family: "'Plus Jakarta Sans', 'EXISTING-BANGLA-FONT', sans-serif", size: 12, weight: 500 };
Chart.defaults.color = '#6e5f52';

// ApexCharts
// chart: { fontFamily: "'Plus Jakarta Sans', 'EXISTING-BANGLA-FONT', sans-serif" }
// xaxis.labels.style / yaxis.labels.style: { fontSize: '12px', fontWeight: 500, colors: '#6e5f52' }
// tooltip: { style: { fontSize: '13px' } }
```

- Axis labels 12px / 500 muted. Tooltip values use the `taka()` format.
- Y-axis money ticks: `৳ 25k` style, tabular.
- Chart titles and subtitles live in the card HTML (`card-title` / `card-sub`), not inside the chart canvas.

---

## 8. Bangla — out of scope for now

- Don't change the Bangla font, its loading, `:lang(bn)` rules, Bangla line-heights or Bangla translations.
- The new role classes apply to Bangla text too only through their sizes and weights; if that makes any Bangla screen look broken, keep the old styling for that Bangla-specific rule and report it instead of redesigning it.
- Bangla typography will be revised separately later.

---

## 9. Don'ts

- No font sizes outside the scale (no `text-4xl`, `text-[17px]`, etc. for text roles).
- No weight 300 or lighter anywhere.
- No weight 900 except the wordmark.
- No Title Case — use sentence case for headings, buttons, labels ("Add grocery purchase").
- No uppercase except `nav-section` overlines.
- No colored or gradient text for headings.
- No emoji in the interface.
- Don't rely on color alone for credit or due — always keep the + / − sign.

---

## 10. How to roll this out (for Claude Code)

1. Find the current font setup: search for `font-family`, `fonts.googleapis`, `fonts.bunny`, `@font-face`, `fontFamily`, `:lang(bn)`, and Tailwind config/theme files. Note which font is used for Bangla and keep it; remove only the old English/Latin UI font.
2. Add the font `<link>` to every layout and the tokens/components from sections 3–4 to `app.css`.
3. Add the `taka()` helper and replace hand-written money formatting everywhere (`number_format`, `'৳'`, `Tk`, etc.).
4. Go through every file in `resources/views/**` (layouts, components, every page in the sidebar, auth, profile, settings, errors, emails, print views, livewire/inertia views if present) and swap ad-hoc text utilities for the role classes, following section 6. Start with shared components (buttons, inputs, tables, badges, modals, cards) so pages inherit the fix, then go page by page.
5. Update chart initialisation per section 7.
6. Run `npm run build` and check that there are no errors.

### Acceptance checklist
- [ ] English UI uses Plus Jakarta Sans; Nunito appears only in the wordmark; the existing Bangla font is still loaded and unchanged.
- [ ] Every page in 6.13 has been checked, not just the dashboard.
- [ ] Dashboard: the stat-card values are the most prominent text after the page H1; the H1 is 30px / 700.
- [ ] All money shows as `৳ 12,345.00`, tabular, never wraps, right-aligned in tables.
- [ ] Sidebar section headers are 11px uppercase overlines; the active nav item is semibold.
- [ ] Chart titles are 16px / 700; axis labels 12px muted in the same font.
- [ ] Switching to Bangla looks the same as before this change (same font, same Bangla rules).
- [ ] No font sizes or weights outside section 4 remain (grep for `text-xl`, `text-2xl`, `text-3xl`, `text-4xl`, `font-light`, `font-thin`, `font-black`).
