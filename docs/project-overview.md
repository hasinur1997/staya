# Staya — Project Requirements

Oct 18, 2026 · @Hasinur Rahman

## Overview

Staya is a web-based mess management system. It manages member profiles, daily grocery purchases, member deposits, one shared mess fund, and monthly reports. Every taka deposited or spent is recorded in an auditable ledger so members and managers can see exactly where money went.

### Confirmed design decisions

| Topic                  | Decision                                                                                                                                                                                                                        |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Balance                | "Current balance" means the **shared mess fund balance**. There is no stored per-member balance.                                                                                                                                |
| Who pays for groceries | The **shared fund** pays. A recorded grocery purchase reduces the fund balance.                                                                                                                                                 |
| Expense allocation     | Total expenses for a month are split **equally among the members active in that month**.                                                                                                                                        |
| Member due/credit      | Calculated **per month only**: member's approved deposits − member's share of expenses.                                                                                                                                         |
| Carry-forward          | **None.** A member's due or credit does not roll into the next month. The shared fund balance itself continues month to month.                                                                                                  |
| Member edit window     | A member can **edit or delete** their own grocery purchase within **30 minutes** of creation (configurable). Afterwards only authorized staff can change it.                                                                    |
| Deletion               | "Delete" is a **soft delete (void)**: the record is hidden from lists, its ledger effect is reversed, and the action is kept in the audit log. Nothing financial is hard-deleted.                                               |
| User ↔ member mapping  | **One user per member.** Admins and managers may exist as users without a member profile.                                                                                                                                       |
| Money storage          | `DECIMAL(12,2)` in BDT. Never floating-point types.                                                                                                                                                                             |
| Access control         | Dynamic **roles and permissions** managed by Admin, enforced on both UI and backend/API.                                                                                                                                        |
| Currency and timezone  | BDT (৳) and Asia/Dhaka.                                                                                                                                                                                                         |
| Reporting month        | Purchases count in the month of their `purchase_date` and deposits in the month of their `deposit_date`, both in Asia/Dhaka. A late approval counts in the deposit's own month.                                                 |
| Future dates           | `purchase_date` and `deposit_date` must be today or earlier (Asia/Dhaka).                                                                                                                                                       |
| Month closing          | A user with `report.close_month` can close a month. Nothing can then be created, edited, voided, approved, reversed or adjusted with a date in that month. It can be reopened with a reason, and both actions are audit-logged. |
| Adjustments vs. shares | Manual adjustments change the fund balance only. They are never part of the expenses split among members.                                                                                                                       |
| Partial months         | A member active on any day of a month pays one full share. No proration.                                                                                                                                                        |
| Rejoining              | Members can leave and rejoin. Each stay is kept as its own record (`member_stays`).                                                                                                                                             |
| Fund floor             | The fund balance never goes below ৳0. If a purchase costs more than the fund holds, the fund pays what it has and the rest becomes a **payable owed to the buyer**, repaid later by staff.                                      |
| Transparency           | Every member can see the monthly per-member table (deposits, share, due/credit) for everyone. Personal details and NID stay restricted.                                                                                         |
| Sign-up                | People self-register by filling in the member form. Admin approves or rejects the registration.                                                                                                                                 |
| Tenancy                | One mess per installation. No `mess_id` scoping.                                                                                                                                                                                |
| Language               | English UI only in v1. All strings go through Laravel's translation helpers so Bangla can be added later.                                                                                                                       |

## User roles and access control

Access is permission-based, not role-hardcoded. Roles are named bundles of permissions that Admin can create and edit without code changes. The three roles below are seeded defaults.

| Role    | Default access                                                                                                                                                                                                                                                                              |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Admin   | All permissions: members, grocery purchases, deposits, ledger, reports, users, roles, permissions, settings. Cannot be deleted or stripped of role management.                                                                                                                              |
| Manager | Day-to-day operations: create/edit grocery purchases, record and review deposits, view members and reports. Exact scope is set by assigned permissions.                                                                                                                                     |
| Member  | View all grocery purchases, create own grocery purchases, edit/delete own within the edit window, submit and cancel own pending deposits, view own deposits, view the monthly report including every member's due/credit (`report.view_all`), view shared fund balance (if setting allows). |

### Access control rules

- Every API endpoint checks the required permission on the server. Hiding a button in the UI is never the only protection.
- Permissions are granular, in the form `resource.action` (e.g. `grocery.create`, `deposit.approve`, `member.view_nid`).
- A user's effective permissions = the union of permissions of their assigned roles.
- A user cannot grant, to anyone, a permission they do not hold themselves.
- A user cannot change their own roles or permissions.
- At least one active Admin must always exist; the system blocks removing the last one.
- Ownership checks apply on top of permissions: `grocery.update_own` only works on the user's own entries and only inside the edit window.
- A user cannot approve a deposit for their own member profile. The only exception is `deposit.approve_own` (Admin only by default). Self-approvals are flagged in the audit log and on the report.
- Nothing dated in a closed month can be changed, whatever permissions the user holds.

### Permission catalog (initial)

| Resource              | Actions                                                                                                      |
| --------------------- | ------------------------------------------------------------------------------------------------------------ |
| Members               | `view`, `create`, `update`, `deactivate`, `delete`, `view_nid`, `approve_registration`                       |
| Grocery purchases     | `view`, `create`, `create_for_others`, `update_own`, `delete_own`, `update_any`, `delete_any`                |
| Deposits              | `view_own`, `view_all`, `create_own`, `create_for_others`, `cancel_own`, `approve`, `approve_own`, `reverse` |
| Ledger                | `view_balance`, `view_transactions`, `adjust`, `reimburse`                                                   |
| Reports               | `view_own`, `view_all`, `close_month`                                                                        |
| Users                 | `view`, `create`, `update`, `deactivate`                                                                     |
| Roles and permissions | `view`, `manage`                                                                                             |
| Settings              | `view`, `update`                                                                                             |
| Audit log             | `view`                                                                                                       |

## Functional requirements

### 1. Member registration and profile

Each member record stores:

- First name and last name
- Image (optional)
- Phone number and email address
- NID number
- Father's name and mother's name
- Permanent address
- NID copy attachment (PDF, JPG or PNG)
- Status: Pending (registration awaiting approval), Rejected, Active or Inactive
- Stay history: one or more stays, each with a join date and an optional leave date

Requirements:

- Admin can create, view, update and deactivate members. Hard delete is allowed only when the member has no financial records.
- Each member is linked to exactly one user account. Creating a member can create its user account in the same step.
- **Self-registration:** anyone can sign up by filling in the full member form (including NID copy) and choosing a password. The member is created as Pending and the account can do nothing yet. A user with `member.approve_registration` approves it (status Active, Member role assigned, first stay opened) or rejects it with a reason shown to the applicant. Approvals and rejections are audit-logged.
- **Stays:** deactivating a member closes the current stay (leave date). Reactivating opens a new stay. A member is active in a month if any stay overlaps any day of that month. Past stays are never rewritten.
- **Inactive members** can still log in read-only: they see their own past deposits and monthly summaries, but cannot create purchases or submit deposits.
- A single-member profile page shows personal details, deposit history, grocery purchases made, and monthly due/credit.
- Validate required fields, Bangladeshi phone format, email format, unique NID, and upload type/size (max 5 MB).
- NID number and NID attachment are visible only with `member.view_nid`. Attachments are served through an authorized download endpoint, never a public URL.

### 2. Grocery purchases

Each grocery purchase contains:

- Purchase date
- Buyer (member)
- One or more line items: item name (free text, with autocomplete from names used before; no item catalog), quantity, unit (kg, gm, liter, ml, pcs, dozen), unit price, line total
- Grand total
- Optional notes and receipt photo
- Created by, created at, last updated by, last updated at

Rules:

- If a member creates a grocery purchase, they are set as the buyer automatically.
- Admin or manager with `grocery.create_for_others` can create one and choose any active member as buyer.
- Line totals and grand total are calculated on the server. Client-sent totals are ignored.
- Quantity and unit price must be greater than zero.
- The purchase date cannot be in the future, before the fund start date, or in a closed month.
- Saving a grocery purchase immediately records an expense in the ledger. There is no approval step.
- If the grand total is more than the fund balance, the ledger expense is only what the fund holds (bringing it to ৳0), and the rest is recorded as a **payable owed to the buyer** (see §4). The full grand total still counts as that month's expense for member shares.
- Editing or voiding a purchase with a payable reduces the unpaid part of the payable first; the fund reversal covers the rest. Any part already reimbursed goes back to the fund.
- All members can view all grocery purchases: date, buyer, total and item details.
- The creator can edit or delete their own entry for **30 minutes** after creation (configurable in settings). After that the entry shows as **Locked** for them.
- After the window, only users with `grocery.update_any` / `grocery.delete_any` can change it.
- An edit posts a reversal of the old ledger amount and a new entry for the new amount, in one database transaction.
- A delete is a void: the entry is hidden, its ledger amount is reversed, and the audit log keeps the full record.
- An owner voiding their own entry inside the edit window gives no reason ("Deleted by owner" is stored). Staff voiding with `grocery.delete_any` must enter a reason.
- The edit window is checked on the server using the server's `created_at`, not the client clock.

### 3. Member deposits

- A member submits a deposit with amount, date, and payment method. **Cash** is the only method in the first release.
- No proof upload is required.
- Status flow: **Pending → Approved**, **Pending → Rejected**, **Pending → Cancelled**, **Approved → Reversed**.
- The deposit date cannot be in the future, before the fund start date, or in a closed month.
- While a deposit is Pending, its member can cancel it (`deposit.cancel_own`). It cannot be edited; the member submits a new one instead. Cancelled deposits never touch the ledger.
- A user with `deposit.approve` reviews it. Rejection requires a reason.
- A reviewer cannot approve a deposit for their own member profile unless they hold `deposit.approve_own` (Admin only by default). Self-approvals are flagged.
- An approved deposit counts in the month of its `deposit_date`, even when approved in a later month.
- Only an approved deposit increases the fund balance. Approval and the ledger entry happen in one transaction.
- A deposit can be approved only once. The approval uses a row lock and status check so double clicks or parallel requests cannot post twice.
- Staff with `deposit.create_for_others` can record a cash deposit on behalf of a member. It still goes through approval unless the recorder also has `deposit.approve`, in which case it can be approved on save (the self-approval rule above still applies).
- An approved deposit cannot be edited. A mistake is fixed with `deposit.reverse`: the deposit becomes **Reversed** with a reason, a ledger reversal is posted, and it no longer counts in the member's approved deposits for its month. If that month is closed, it must be reopened first.
- A reversal is blocked if it would take the fund balance below ৳0.
- The record keeps submitter, reviewer, submitted at, reviewed at and rejection reason.

### 4. Shared fund ledger and balance

The ledger is the single source of truth for money. The fund balance is always the sum of ledger entries.

| Entry type               | Effect on fund                                |
| ------------------------ | --------------------------------------------- |
| Opening balance          | + (set once in settings)                      |
| Approved deposit         | + amount                                      |
| Grocery purchase expense | − amount                                      |
| Reversal                 | opposite of the entry it reverses             |
| Manual adjustment        | + or −, requires `ledger.adjust` and a reason |
| Buyer reimbursement      | − amount, requires `ledger.reimburse`         |

- Ledger entries are append-only. They are never updated or deleted.
- No user can type in a balance directly.
- The current balance may be cached for speed, but it must always equal the ledger sum.
- For any period the system shows opening balance, total deposits, total expenses, adjustments, reimbursements and closing balance.

#### Fund floor and buyer payables

- The fund balance never goes below ৳0.
- A grocery purchase larger than the balance posts an expense equal to the balance, and the shortfall becomes a **payable** owed to the buyer (`buyer_payables`).
- A user with `ledger.reimburse` records a reimbursement when they hand the buyer cash. It posts a ledger debit and reduces the payable. It cannot exceed the fund balance or the amount still owed.
- Deposit reversals, negative adjustments and reimbursements are blocked if they would take the balance below ৳0. The error says how much is missing. Only purchases create payables.
- Open payables are shown on the dashboard and in reports.
- Every check against the balance runs inside the transaction with the fund balance row locked (`lockForUpdate()`), so parallel requests cannot overdraw it.

### 5. Expense allocation and member due/credit

Each month's expenses are split equally among members active in that month. A member counts as active for a month if any of their stays overlaps any day of that month, and pays one full share (no proration).

- **Total grocery expenses in month** = the grand totals of non-voided purchases whose `purchase_date` falls in the month (Asia/Dhaka), including any part covered by a buyer payable.
- **Approved deposits in month** = approved (not reversed) deposits whose `deposit_date` falls in the month.
- Manual adjustments and reimbursements are not expenses and are never shared.

```latex
\text{Share per member} = \frac{\text{Total grocery expenses in month}}{\text{Active members in month}}
```

```latex
\text{Member due/credit} = \text{Member's approved deposits in month} - \text{Share per member}
```

- A positive result is **credit**; a negative result is **due**.
- Results are per month only. Nothing carries into the next month.
- The buyer does not personally owe the purchase cost. Buying only records who spent fund money.
- Example: expenses ৳24,000 and 8 active members give a share of ৳3,000. A member who deposited ৳3,500 has ৳500 credit; one who deposited ৳2,000 has ৳1,000 due.
- Rounding: the share is rounded to 2 decimal places. Any leftover paisa is noted in the report, not assigned to a member.

### 6. Reports

Filters: month and year, custom date range, and buyer/member.

The monthly report shows:

- Grocery purchase list: date, buyer, total, with a link to details
- Total deposits (approved), total expenses, adjustments
- Opening and closing shared fund balance
- Active member count and share per member
- Per-member table: approved deposits, share, due/credit (visible to every member with `report.view_all`, which the Member role has by default)
- Pending and rejected deposit summary
- Open buyer payables and reimbursements in the period
- Self-approved deposits, flagged
- Month status: Open or Closed (with who closed it and when)

#### Closing a month

- A user with `report.close_month` closes a month once its figures are settled. After that, nothing dated in that month can be created, edited, voided, approved, rejected, reversed or adjusted.
- A closed month can be reopened by a user with `report.close_month`, with a required reason.
- Close and reopen are both audit-logged.

The grocery purchase detail page shows items, quantities, units, prices, total, buyer, date, receipt, and audit history (with `audit.view`).

PDF/CSV export is out of scope for the first release. Reports are print-friendly via a print stylesheet.

### 7. Member dashboard

A logged-in member can:

- Browse all grocery purchases by month/year or custom date range
- Create a grocery purchase and edit or delete their own within the edit window, with a visible countdown and a Locked badge after
- Submit a deposit, cancel it while Pending, and see its status (Pending, Approved, Rejected with reason, Cancelled, Reversed with reason)
- See their own deposit history
- See their own monthly summary: deposits, share, due/credit
- See the monthly per-member table for all members
- See any amount the fund owes them as buyer
- See the shared fund balance, if the setting allows

Inactive members see the read-only parts of this dashboard only.

### 8. User, role and permission management

- Login is by email and password. Public sign-up is open, but a new account has no access until its member registration is approved (see §1).
- Admin can create, update and deactivate user accounts.
- Admin links a user to a member profile, or leaves it unlinked for admin/manager-only accounts.
- Admin can create, rename and delete roles, and tick permissions per role from the catalog.
- A role in use cannot be deleted until users are moved off it.
- Deactivated users cannot log in; their history stays intact.

### 9. Mess settings

| Setting                              | Default                                        |
| ------------------------------------ | ---------------------------------------------- |
| Mess name and address                | —                                              |
| Currency                             | BDT (৳)                                        |
| Timezone                             | Asia/Dhaka                                     |
| Opening fund balance and start date  | ৳0.00, set at setup                            |
| Member grocery edit window           | 30 minutes                                     |
| Members can view shared fund balance | Yes                                            |
| Allocation method                    | Equal split among active members (fixed in v1) |
| Max upload size                      | 5 MB                                           |
| Language                             | English (fixed in v1)                          |

The opening balance can be changed only before any ledger entry exists. After that, changes go through a manual adjustment. No purchase or deposit can be dated before the fund start date.

## Audit, security and data protection

### Audit log

- Logged actions: create, update, void of grocery purchases; deposit submit, cancel, approve (flagging self-approvals), reject, reverse; manual adjustments; buyer payables and reimbursements; month close and reopen; member registration approve/reject, create/update/deactivate/reactivate; NID views and downloads; role and permission changes; settings changes; successful logins, failed login attempts (email tried, IP) and logouts.
- Each entry stores actor, action, entity type and id, timestamp, IP address, and before/after values as JSON.
- The audit log is append-only and visible only with `audit.view`.

### Security

- Passwords hashed with bcrypt or Argon2. Secure, HTTP-only session cookies. Login rate limiting.
- Password reset by email link with a short expiry.
- CSRF protection on all state-changing requests.
- Server-side validation and sanitization of every input. Parameterized queries only.
- Uploads stored outside the public web root, renamed on save, type checked by content, and downloaded only through an authorized endpoint.
- All timestamps stored in UTC and shown in Asia/Dhaka.
- Daily database backups and error logging without exposing stack traces to users.

## Key business rules

1. Pending and rejected deposits never affect the fund balance.
2. An approved deposit increases the fund balance exactly once.
3. A grocery purchase reduces the fund balance exactly once when saved.
4. Line totals and grand totals are calculated by the server.
5. Ledger entries are append-only; corrections are reversals or adjustments with a reason.
6. Deleting a grocery purchase is a void with a ledger reversal, never a hard delete.
7. Members can edit or delete only their own grocery purchases, and only within the configured window (default 30 minutes).
8. Monthly expenses are split equally among members active that month.
9. Member due/credit is calculated per month with no carry-forward.
10. Every financial change is traceable to a user and timestamp.
11. Only users with the right permission can approve deposits, correct records, or manage roles.
12. No user can grant a permission they do not hold, or change their own permissions.
13. All money is stored as `DECIMAL(12,2)`.
14. Purchases and deposits count in the month of their own date (Asia/Dhaka); dates cannot be in the future.
15. Nothing dated in a closed month can change until the month is reopened with a reason.
16. The fund balance never goes below ৳0; a purchase shortfall becomes a payable owed to the buyer.
17. Manual adjustments and reimbursements are never part of member shares.
18. No one approves their own deposit without `deposit.approve_own`.

## Core data entities

| Table                    | Key fields                                                                                                                                                                                                                                                            | Relates to                                    |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| `users`                  | id, name, email, phone, password\_hash, is\_active, last\_login\_at                                                                                                                                                                                                   | `members` (0..1), `user_roles`                |
| `members`                | id, user\_id (unique), first\_name, last\_name, phone, email, nid\_number (unique), father\_name, mother\_name, permanent\_address, nid\_file\_path, photo\_path, status (pending/rejected/active/inactive), rejection\_reason, approved\_by, approved\_at            | `users` (1), `member_stays`                   |
| `member_stays`           | id, member\_id, joined\_on, left\_on (nullable)                                                                                                                                                                                                                       | `members`                                     |
| `roles`                  | id, name, slug, is\_system                                                                                                                                                                                                                                            | `role_permissions`, `user_roles`              |
| `permissions`            | id, key (e.g. `deposit.approve`), group, description                                                                                                                                                                                                                  | `role_permissions`                            |
| `role_permissions`       | role\_id, permission\_id                                                                                                                                                                                                                                              | `roles`, `permissions`                        |
| `user_roles`             | user\_id, role\_id                                                                                                                                                                                                                                                    | `users`, `roles`                              |
| `grocery_purchases`      | id, purchase\_date, buyer\_member\_id, grand\_total DECIMAL(12,2), notes, receipt\_path, status (active/voided), void\_reason, created\_by, updated\_by, timestamps                                                                                                   | `members`, `grocery_purchase_items`           |
| `grocery_purchase_items` | id, grocery\_purchase\_id, item\_name, quantity DECIMAL(10,3), unit, unit\_price DECIMAL(12,2), line\_total DECIMAL(12,2)                                                                                                                                             | `grocery_purchases`                           |
| `deposits`               | id, member\_id, amount DECIMAL(12,2), deposit\_date, method (cash), status (pending/approved/rejected/cancelled/reversed), submitted\_by, reviewed\_by, reviewed\_at, rejection\_reason, is\_self\_approved, reversed\_by, reversed\_at, reversal\_reason, timestamps | `members`, `users`                            |
| `buyer_payables`         | id, grocery\_purchase\_id, member\_id, amount DECIMAL(12,2), amount\_reimbursed DECIMAL(12,2), timestamps                                                                                                                                                             | `grocery_purchases`, `members`                |
| `month_closures`         | id, month (first day of month), closed\_by, closed\_at, reopened\_by, reopened\_at, reopen\_reason                                                                                                                                                                    | `users`                                       |
| `ledger_entries`         | id, entry\_date, type, direction (credit/debit), amount DECIMAL(12,2), source\_type, source\_id, reverses\_entry\_id, reason, created\_by, created\_at                                                                                                                | `deposits`, `grocery_purchases` (polymorphic) |
| `audit_logs`             | id, actor\_id, action, entity\_type, entity\_id, before JSON, after JSON, ip\_address, created\_at                                                                                                                                                                    | `users`                                       |
| `mess_settings`          | key, value                                                                                                                                                                                                                                                            | —                                             |

Indexes on `grocery_purchases.purchase_date`, `deposits(member_id, status)`, `deposits.deposit_date`, `member_stays(member_id, joined_on)`, `month_closures.month` (unique), `ledger_entries.entry_date` and `ledger_entries(source_type, source_id)`. Monthly allocation is computed from these tables at report time, so no `expense_allocations` table is needed in v1.

## Release scope and acceptance criteria

**In v1:** everything above. **Out of v1:** PDF/CSV export, non-cash payment methods, deposit proof uploads, meal-based or custom allocation, prorated shares, carry-forward of member balances, notifications, Bangla UI, multiple messes per installation, a managed grocery item catalog.

- [ ] Admin can register a member with a linked user account; NID data is visible only to users with `member.view_nid`.
- [ ] A member submits a cash deposit; the fund balance changes only after approval, and only once even with repeated approve requests.
- [ ] A rejected deposit shows its reason to the member and never touches the balance.
- [ ] A grocery purchase with multiple items saves with server-calculated totals and reduces the balance once.
- [ ] A member can edit or delete their own grocery purchase within 30 minutes; after that the API returns 403 and the UI shows Locked.
- [ ] Editing or deleting a grocery purchase posts reversal entries; the ledger sum always equals the displayed balance.
- [ ] The monthly report shows opening/closing fund balance, deposits, expenses, active member count, share per member and each member's due/credit.
- [ ] Due/credit for one month is unaffected by any other month.
- [ ] A new role created in the UI with chosen permissions is enforced by the API without code changes.
- [ ] A user cannot grant a permission they lack or change their own roles; the last Admin cannot be removed.
- [ ] Every financial create, edit, void, approval, rejection and adjustment appears in the audit log with before/after values.
- [ ] A self-registered applicant has no access until approved; a rejected applicant sees the reason.
- [ ] A deposit dated Oct 30 and approved Nov 2 counts in October; future-dated purchases and deposits are rejected.
- [ ] Nothing dated in a closed month can be created or changed until it is reopened with a reason.
- [ ] A purchase larger than the fund leaves the balance at ৳0 and creates a payable to the buyer; the full amount counts in member shares; reimbursement reduces the payable and never overdraws the fund.
- [ ] Deposit reversals and negative adjustments that would make the balance negative are rejected.
- [ ] A reviewer cannot approve their own deposit without `deposit.approve_own`; self-approvals are flagged.
- [ ] A member who left and rejoined is counted active only in months overlapping one of their stays.
- [ ] An inactive member can log in and view their history but gets 403 on creating purchases or deposits.
- [ ] Failed login attempts appear in the audit log.
