# Staya — Build Plan

Derived from `docs/project-overview.md` (requirements), `docs/database-schema.md` (tables) and
`CLAUDE.md` (rules). Phases are listed in build order; each one depends only on the phases above it.
Acceptance criteria from the overview are numbered **AC-1 … AC-20** below and referenced from every
phase's "Done when" checklist.

## Contents

- [Acceptance criteria index](#acceptance-criteria-index)
- [Before Phase 1](#before-phase-1)
- [Definition of done (every phase)](#definition-of-done-every-phase)
- [Phase 1 — Access control](#phase-1--access-control)
- [Phase 2 — Settings](#phase-2--settings)
- [Phase 3 — Ledger](#phase-3--ledger)
- [Phase 4 — Members](#phase-4--members)
- [Phase 5 — Deposits](#phase-5--deposits)
- [Phase 6 — Grocery purchases](#phase-6--grocery-purchases)
- [Phase 7 — Reports](#phase-7--reports)
- [Phase 8 — Member dashboard](#phase-8--member-dashboard)
- [Phase 9 — Audit viewer](#phase-9--audit-viewer)
- [Coverage matrix](#coverage-matrix)

---

## Acceptance criteria index

Copied from [Release scope and acceptance criteria](project-overview.md#release-scope-and-acceptance-criteria),
in the same order.

| ID                      | Criterion                                                                                                                                                                                             |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| <a id="ac-1"></a>AC-1   | Admin can register a member with a linked user account; NID data is visible only to users with `member.view_nid`.                                                                                     |
| <a id="ac-2"></a>AC-2   | A member submits a cash deposit; the fund balance changes only after approval, and only once even with repeated approve requests.                                                                     |
| <a id="ac-3"></a>AC-3   | A rejected deposit shows its reason to the member and never touches the balance.                                                                                                                      |
| <a id="ac-4"></a>AC-4   | A grocery purchase with multiple items saves with server-calculated totals and reduces the balance once.                                                                                              |
| <a id="ac-5"></a>AC-5   | A member can edit or delete their own grocery purchase within 30 minutes; after that the API returns 403 and the UI shows Locked.                                                                     |
| <a id="ac-6"></a>AC-6   | Editing or deleting a grocery purchase posts reversal entries; the ledger sum always equals the displayed balance.                                                                                    |
| <a id="ac-7"></a>AC-7   | The monthly report shows opening/closing fund balance, deposits, expenses, active member count, share per member and each member's due/credit.                                                        |
| <a id="ac-8"></a>AC-8   | Due/credit for one month is unaffected by any other month.                                                                                                                                            |
| <a id="ac-9"></a>AC-9   | A new role created in the UI with chosen permissions is enforced by the API without code changes.                                                                                                     |
| <a id="ac-10"></a>AC-10 | A user cannot grant a permission they lack or change their own roles; the last Admin cannot be removed.                                                                                               |
| <a id="ac-11"></a>AC-11 | Every financial create, edit, void, approval, rejection and adjustment appears in the audit log with before/after values.                                                                             |
| <a id="ac-12"></a>AC-12 | A self-registered applicant has no access until approved; a rejected applicant sees the reason.                                                                                                       |
| <a id="ac-13"></a>AC-13 | A deposit dated Oct 30 and approved Nov 2 counts in October; future-dated purchases and deposits are rejected.                                                                                        |
| <a id="ac-14"></a>AC-14 | Nothing dated in a closed month can be created or changed until it is reopened with a reason.                                                                                                         |
| <a id="ac-15"></a>AC-15 | A purchase larger than the fund leaves the balance at ৳0 and creates a payable to the buyer; the full amount counts in member shares; reimbursement reduces the payable and never overdraws the fund. |
| <a id="ac-16"></a>AC-16 | Deposit reversals and negative adjustments that would make the balance negative are rejected.                                                                                                         |
| <a id="ac-17"></a>AC-17 | A reviewer cannot approve their own deposit without `deposit.approve_own`; self-approvals are flagged.                                                                                                |
| <a id="ac-18"></a>AC-18 | A member who left and rejoined is counted active only in months overlapping one of their stays.                                                                                                       |
| <a id="ac-19"></a>AC-19 | An inactive member can log in and view their history but gets 403 on creating purchases or deposits.                                                                                                  |
| <a id="ac-20"></a>AC-20 | Failed login attempts appear in the audit log.                                                                                                                                                        |

---

## Before Phase 1

Resolve the open questions in [database-schema.md](database-schema.md#open-questions-for-review)
before the phase that needs them:

| Question                                             | Needed by |
| ---------------------------------------------------- | --------- |
| Spatie package versions / published migration shapes | Phase 1   |
| NID encryption at rest (`nid_number_hash`)           | Phase 4   |
| Phone uniqueness                                     | Phase 4   |
| Dropping `members.email`                             | Phase 4   |
| Item-name autocomplete source                        | Phase 6   |

Get approval to install `spatie/laravel-permission` and `spatie/laravel-activitylog` (see `CLAUDE.md`).

## Definition of done (every phase)

These apply to every phase in addition to its own checklist.

- [ ] Every new controller action is authorized through a Policy that checks `resource.action`
      permissions, never role names. Each guarded action has a feature test for the 403 path.
- [ ] Computed abilities (`can.*`) are passed to the Inertia page; the UI only mirrors them.
- [ ] Money columns are `DECIMAL(12,2)`, quantities `DECIMAL(10,3)`; money is cast `decimal:2` and
      computed with bcmath. No floats in PHP or TypeScript.
- [ ] Timestamps stored in UTC, shown in Asia/Dhaka; business dates evaluated in Asia/Dhaka.
- [ ] Every financial change and its ledger entries run in one transaction with the `fund` row locked.
- [ ] Every auditable action for this phase writes an activity-log row with actor, IP and
      before/after values ([AC-11](#ac-11)).
- [ ] Every model has a factory; UI strings go through translation helpers.
- [ ] Routes are named and consumed through Wayfinder; generated folders are not hand-edited.
- [ ] `composer ci:check` passes (Pint, PHPStan, PHPUnit, `vp check`, `tsc`).

---

## Phase 1 — Access control

Roles, permissions, user management, login hardening and the audit-log foundation that every later
phase writes to.

**Deliverables**

- Install and configure `spatie/laravel-permission` (`teams => false`) and
  `spatie/laravel-activitylog`; publish and run their migrations.
- Schema additions: `permissions.group`, `permissions.description`, `roles.is_system`;
  `activity_log.ip_address` and `created_at` index; `users.is_active`, `users.last_login_at`.
- Morph map enforced for all models (`user`, `member`, `deposit`, …) per the schema doc.
- Custom append-only `Activity` model (throws on update/delete) that records the request IP on every
  row; `activitylog:clean` never scheduled.
- Permission catalog seeder (every `resource.action` from the overview, grouped) and role seeder
  for the three system roles: Admin (all), Manager, Member, with their default permissions.
- Policies for users and roles; a shared guard for "cannot grant what you lack", "cannot change own
  roles", "cannot remove the last active Admin", "system roles cannot be renamed or deleted", "Admin
  keeps role management", "role in use cannot be deleted".
- Pages: user list/create/edit/deactivate; role list/create/rename/delete with a permission
  checklist grouped by resource.
- Fortify login changes: deactivated users cannot log in; `last_login_at` is set; login, failed
  login (email tried, IP) and logout are audit-logged; login rate limiting confirmed.
- Audit entries for user create/update/deactivate and every role/permission change.
- Navigation shows only items the user has permission for.

**Done when**

- [ ] A role created in the UI with a chosen permission set grants exactly those API actions and is
      denied the rest, with no code change ([AC-9](#ac-9)).
- [ ] Granting a permission the actor lacks returns 403 / a validation error ([AC-10](#ac-10)).
- [ ] Changing one's own roles is rejected ([AC-10](#ac-10)).
- [ ] Removing or deactivating the last active Admin, or stripping Admin of role management, is
      rejected ([AC-10](#ac-10)).
- [ ] A role still assigned to users cannot be deleted; system roles cannot be renamed or deleted.
- [ ] A deactivated user cannot log in; their history is intact.
- [ ] A failed login writes an activity row with the email tried and IP ([AC-20](#ac-20)).
- [ ] Role and permission changes appear in the activity log with before/after values ([AC-11](#ac-11)).
- [ ] An attempt to update or delete an activity-log row throws.

---

## Phase 2 — Settings

The single `mess_settings` row that later phases read for dates, windows and visibility.

**Deliverables**

- `mess_settings` migration (single typed row) and seeder.
- Settings model/accessor used everywhere: fund start date, edit window minutes,
  `members_can_view_balance`, max upload size, mess name/address. Currency and timezone shown
  read-only (fixed in v1).
- A shared date helper: "today in Asia/Dhaka", month boundaries in Asia/Dhaka, and validation rules
  for "not in the future" and "not before fund start date" (in `app/Concerns`).
- Shared money display helper on the frontend (formats ৳ strings, no arithmetic).
- Settings page under the existing settings area, guarded by `settings.view` / `settings.update`.
- Audit entries for every settings change.
- Opening balance and fund start date are editable here; posting the opening ledger entry and
  locking the field once the ledger has entries are completed in Phase 3.

**Done when**

- [ ] Users without `settings.update` get 403 on save; without `settings.view` get 403 on the page.
- [ ] Changing the edit window, upload limit or balance visibility is read by code without a
      deploy (verified by a test that changes the setting and observes the effect).
- [ ] The date rules reject a future date and a date before fund start, evaluated in Asia/Dhaka
      (groundwork for [AC-13](#ac-13)).
- [ ] Settings changes appear in the activity log with before/after values ([AC-11](#ac-11)).

---

## Phase 3 — Ledger

The fund, the append-only ledger, the balance floor, manual adjustments and the closed-month guard.
Everything financial after this phase posts through it.

**Deliverables**

- Migrations: `ledger_entries`, `fund` (seeded single row), `month_closures`.
- Enums: `LedgerEntryType`, `LedgerDirection`.
- `LedgerEntry` model that throws on update/delete; `reverses_entry_id` unique.
- One posting service/action used by all later phases: locks `fund` with `lockForUpdate()`, checks
  the ৳0 floor (error states how much is missing), inserts entries, updates the cached balance, all
  in one transaction. Reversal helper that posts the opposite entry with a reason.
- Closed-month guard: a reusable check (validation rule + action-level assertion) that blocks any
  create/edit/void/approve/reject/reverse/adjust dated in a closed month. Close/reopen UI comes in
  Phase 7; this phase owns the table and the guard.
- Opening balance: posting the `opening_balance` entry from settings; the field becomes read-only
  once any ledger entry exists.
- Manual adjustment action and form (`ledger.adjust`, reason required, ± direction, date rules).
- Ledger page: transaction list with filters (`ledger.view_transactions`); fund balance widget
  (`ledger.view_balance`, plus the members-visibility setting).
- Period summary query (opening, deposits, expenses, adjustments, reimbursements, closing) for use
  by Reports.
- A reusable test assertion "fund.balance equals ledger sum", used after every financial action in
  this and later phases.
- Audit entries for adjustments and opening-balance changes.

**Done when**

- [ ] The displayed balance equals the ledger sum after every action tested ([AC-6](#ac-6)).
- [ ] Updating or deleting a ledger entry throws; an entry cannot be reversed twice.
- [ ] A negative adjustment that would take the fund below ৳0 is rejected with the missing amount
      ([AC-16](#ac-16)).
- [ ] An adjustment without a reason, or without `ledger.adjust`, is rejected (403 path tested).
- [ ] An adjustment dated in a closed month is rejected ([AC-14](#ac-14)).
- [ ] The opening balance cannot be changed once a ledger entry exists.
- [ ] Parallel postings cannot overdraw the fund (locking verified by test).
- [ ] Adjustments appear in the activity log with before/after values ([AC-11](#ac-11)).

---

## Phase 4 — Members

Member profiles, stays, self-registration and approval, NID protection.

**Deliverables**

- Migrations: `members`, `member_stays`. Enum `MemberStatus`.
- Private disk for NID copies and photos; files renamed on save, type checked by content, size
  limit from settings.
- Admin create-member flow that creates the linked user account in the same step.
- Public self-registration: full member form + password; creates a Pending member whose account can
  do nothing until approved (middleware/gate for pending and rejected accounts).
- Approve registration (`member.approve_registration`): status Active, Member role assigned, first
  stay opened. Reject with reason, shown to the applicant on login.
- Update, deactivate (closes current stay), reactivate (opens new stay); stay rules enforced
  (one open stay, no overlap, past stays never edited).
- "Active in month" query in Asia/Dhaka, shared with Reports.
- Hard delete only when the member has no purchases, deposits or payables.
- Inactive members: login allowed, read-only access only.
- Validation: required fields, Bangladeshi phone format, email, unique NID (10/13/17 digits),
  upload type/size.
- NID number and attachment shown only with `member.view_nid`; authorized download route; NID
  views and downloads audit-logged.
- Pages: member list, create/edit, registration queue, profile page (personal details now; deposit
  history, purchases and monthly due/credit sections are filled in by Phases 5–7).
- Audit entries for registration approve/reject, create, update, deactivate, reactivate.

**Done when**

- [ ] Admin creates a member with a linked user in one step ([AC-1](#ac-1)).
- [ ] NID number and file are absent from responses and the download route returns 403 without
      `member.view_nid` ([AC-1](#ac-1)).
- [ ] A self-registered applicant gets 403 / is redirected on every app route until approved
      ([AC-12](#ac-12)).
- [ ] A rejected applicant sees the rejection reason ([AC-12](#ac-12)).
- [ ] A member who left and rejoined is active only in months overlapping one of their stays,
      including partial months ([AC-18](#ac-18)).
- [ ] An inactive member can log in and see the read-only pages (create actions tested in Phases 5
      and 6) ([AC-19](#ac-19)).
- [ ] Hard delete is refused for a member with financial records.
- [ ] Member changes, registration decisions and NID views appear in the activity log ([AC-11](#ac-11)).

---

## Phase 5 — Deposits

Cash deposits through the full status flow, with exactly-once approval and the self-approval rule.

**Deliverables**

- Migration `deposits`; enums `DepositStatus`, `DepositMethod` (cash only).
- Actions: submit own, record for others, cancel own (pending only), approve, reject (reason
  required), reverse (reason required, floor check), each in its own class under `app/Actions/Deposits`.
- Approval: row lock + `status = pending` check + ledger posting in one transaction; idempotent
  under repeated or parallel requests.
- Self-approval rule: blocked unless `deposit.approve_own`; sets `is_self_approved`.
- Record-for-others with "approve on save" when the recorder holds `deposit.approve`.
- Date rules (not future, not before fund start, not in a closed month) on submit, approve, reject
  and reverse.
- Inactive members get 403 on submit.
- Pages: my deposits (submit, cancel, status with reasons), review queue, all deposits
  (`deposit.view_all`); deposit history section on the member profile.
- Audit entries for submit, cancel, approve (self-approval flagged), reject, reverse.

**Done when**

- [ ] A pending deposit does not change the balance; approval increases it once, even with
      repeated or concurrent approve requests ([AC-2](#ac-2)).
- [ ] A rejected deposit shows its reason to the member and leaves the balance unchanged ([AC-3](#ac-3)).
- [ ] Cancelled deposits never create ledger entries.
- [ ] Approving one's own deposit without `deposit.approve_own` returns 403; with it, the deposit is
      flagged `is_self_approved` ([AC-17](#ac-17)).
- [ ] A future-dated deposit is rejected ([AC-13](#ac-13)).
- [ ] A reversal that would take the balance below ৳0 is rejected ([AC-16](#ac-16)).
- [ ] Any deposit action dated in a closed month is rejected ([AC-14](#ac-14)).
- [ ] An inactive member gets 403 when submitting a deposit ([AC-19](#ac-19)).
- [ ] Approved deposits cannot be edited.
- [ ] Every deposit state change appears in the activity log ([AC-11](#ac-11)); ledger sum equals
      balance after each ([AC-6](#ac-6)).

---

## Phase 6 — Grocery purchases

Multi-item purchases with server totals, the edit window, voids, buyer payables and reimbursements.

**Deliverables**

- Migrations `grocery_purchases`, `grocery_purchase_items`, `buyer_payables`; enums
  `GroceryPurchaseStatus`, `GroceryUnit`.
- Create action: buyer = current member, or any active member with `grocery.create_for_others`;
  line and grand totals computed on the server with bcmath; client totals ignored; quantity and
  unit price > 0; date rules; optional receipt on the private disk.
- Fund floor: expense posted up to the balance (skipped at ৳0), shortfall stored as a buyer payable.
- Edit action: reversal of the old ledger amount + new entry, payable adjusted first, items
  replaced, one transaction.
- Void action: status voided, reason (owner void stores "Deleted by owner"; staff must give one),
  payable unpaid part reduced first, reimbursed part returned to the fund, ledger reversal.
- Edit-window policy based on server `created_at` and the settings value; `update_any` /
  `delete_any` bypass the window.
- Reimbursement action (`ledger.reimburse`): ledger debit, reduces payable, never exceeds the
  balance or amount owed.
- Item-name autocomplete endpoint.
- Pages: purchase list (voided hidden, date/month filters), create/edit form with line items and
  live display of server-confirmed totals, detail page (items, receipt), open payables list with
  reimburse action. `can.update` / `can.delete` and window expiry passed to the UI; Locked badge
  and countdown.
- Purchases-made section on the member profile.
- Audit entries for create, edit (old and new item lists), void, payable changes, reimbursements.

**Done when**

- [ ] A multi-item purchase saves with server-calculated line and grand totals, ignoring tampered
      client totals, and reduces the balance once ([AC-4](#ac-4)).
- [ ] The owner can edit/void within the window; after it the API returns 403 and the UI shows
      Locked ([AC-5](#ac-5)).
- [ ] Edit and void post reversal entries and ledger sum equals balance afterwards ([AC-6](#ac-6)).
- [ ] A purchase larger than the fund leaves the balance at ৳0 and creates a payable for the rest
      ([AC-15](#ac-15)).
- [ ] Reimbursement reduces the payable and cannot overdraw the fund or exceed the amount owed
      ([AC-15](#ac-15)).
- [ ] A future-dated purchase is rejected ([AC-13](#ac-13)); one dated in a closed month cannot be
      created, edited or voided ([AC-14](#ac-14)).
- [ ] An inactive member gets 403 creating a purchase ([AC-19](#ac-19)).
- [ ] Staff void without a reason is rejected; voided purchases are hidden from lists.
- [ ] Create, edit, void and reimbursement appear in the activity log with before/after values
      ([AC-11](#ac-11)).

---

## Phase 7 — Reports

Monthly figures, member shares and due/credit, month closing.

**Deliverables**

- Report calculation action: for a month (Asia/Dhaka) or date range, compute opening and closing
  balance, approved deposits, expenses (full grand totals incl. payable parts), adjustments,
  reimbursements, active member count, share per member (rounded to 2 places, leftover paisa
  reported), per-member deposits/share/due-credit, pending and rejected deposit summary, open
  payables, self-approved deposits.
- Filters: month/year, custom range, buyer/member.
- Monthly report page (`report.view_all` for the per-member table, `report.view_own` for one's own
  row), print stylesheet.
- Month close and reopen (`report.close_month`, reopen reason required) with status shown on the
  report; uses the Phase 3 guard.
- Monthly due/credit section on the member profile.
- Audit entries for close and reopen.

**Done when**

- [ ] The report shows opening/closing balance, deposits, expenses, active member count, share per
      member and each member's due/credit, matching a hand-worked fixture (e.g. ৳24,000 / 8
      members = ৳3,000) ([AC-7](#ac-7)).
- [ ] Changing another month's data does not change this month's due/credit ([AC-8](#ac-8)).
- [ ] A deposit dated Oct 30 and approved Nov 2 counts in October ([AC-13](#ac-13)).
- [ ] A purchase partly covered by a payable counts in full in member shares; adjustments and
      reimbursements are not shared ([AC-15](#ac-15)).
- [ ] A rejoined member counts only in months overlapping a stay ([AC-18](#ac-18)).
- [ ] Self-approved deposits are flagged on the report ([AC-17](#ac-17)).
- [ ] After closing a month, creating or changing anything dated in it is rejected across deposits,
      purchases and adjustments; after reopening with a reason it is allowed again ([AC-14](#ac-14)).
- [ ] Close/reopen without `report.close_month` returns 403; both appear in the activity log
      ([AC-11](#ac-11)).

---

## Phase 8 — Member dashboard

The member's home screen, assembled from the features built above.

**Deliverables**

- Dashboard page for members: fund balance (if the setting allows and `ledger.view_balance`), own
  monthly summary (deposits, share, due/credit), amount the fund owes them, recent deposits with
  status and reasons, quick links to submit a deposit and create a purchase.
- Grocery browsing by month/year or range, with countdown and Locked badge on own entries.
- Monthly per-member table for all members.
- Read-only variant for inactive members (create actions hidden, server still returns 403).
- Staff dashboard variant: pending deposit count, pending registrations, open payables.

**Done when**

- [ ] A member sees their own summary, deposit statuses with reasons and amount owed to them
      ([AC-3](#ac-3), [AC-15](#ac-15)).
- [ ] The balance widget is hidden when the setting is off, and the balance endpoint/prop is not
      sent.
- [ ] Own purchases show a countdown inside the window and Locked after it ([AC-5](#ac-5)).
- [ ] An inactive member sees only read-only parts and gets 403 on create endpoints ([AC-19](#ac-19)).
- [ ] No pending or rejected applicant can reach the dashboard ([AC-12](#ac-12)).

---

## Phase 9 — Audit viewer

A read-only UI over the activity log built up since Phase 1.

**Deliverables**

- Audit log page (`audit.view` only): list with filters by date range, actor, log name/action and
  subject; detail view showing before/after diff, IP and batch.
- Audit history panel on the grocery purchase detail page and other financial detail pages, shown
  only with `audit.view`.
- Times displayed in Asia/Dhaka.
- A completeness test sweep: one test per logged action from the overview's audit list.

**Done when**

- [ ] Users without `audit.view` get 403 on the page and the history panel is absent.
- [ ] Every financial create, edit, void, approval, rejection and adjustment is listed with
      before/after values, actor and IP ([AC-11](#ac-11)).
- [ ] Failed logins are listed with the email tried and IP ([AC-20](#ac-20)).
- [ ] Self-approvals are visibly flagged ([AC-17](#ac-17)).
- [ ] The viewer offers no edit or delete action, and the model rejects them.

---

## Coverage matrix

Where each acceptance criterion is built (●) and where it is re-verified (○).

| AC              | 1 Access | 2 Settings | 3 Ledger | 4 Members | 5 Deposits | 6 Grocery | 7 Reports | 8 Dashboard | 9 Audit |
| --------------- | :------: | :--------: | :------: | :-------: | :--------: | :-------: | :-------: | :---------: | :-----: |
| [AC-1](#ac-1)   |          |            |          |     ●     |            |           |           |             |         |
| [AC-2](#ac-2)   |          |            |          |           |     ●      |           |           |             |         |
| [AC-3](#ac-3)   |          |            |          |           |     ●      |           |           |      ○      |         |
| [AC-4](#ac-4)   |          |            |          |           |            |     ●     |           |             |         |
| [AC-5](#ac-5)   |          |            |          |           |            |     ●     |           |      ○      |         |
| [AC-6](#ac-6)   |          |            |    ●     |           |     ○      |     ●     |           |             |         |
| [AC-7](#ac-7)   |          |            |          |           |            |           |     ●     |             |         |
| [AC-8](#ac-8)   |          |            |          |           |            |           |     ●     |             |         |
| [AC-9](#ac-9)   |    ●     |            |          |           |            |           |           |             |         |
| [AC-10](#ac-10) |    ●     |            |          |           |            |           |           |             |         |
| [AC-11](#ac-11) |    ●     |     ○      |    ○     |     ○     |     ○      |     ○     |     ○     |             |    ●    |
| [AC-12](#ac-12) |          |            |          |     ●     |            |           |           |      ○      |         |
| [AC-13](#ac-13) |          |     ○      |          |           |     ●      |     ●     |     ●     |             |         |
| [AC-14](#ac-14) |          |            |    ●     |           |     ○      |     ○     |     ●     |             |         |
| [AC-15](#ac-15) |          |            |          |           |            |     ●     |     ●     |      ○      |         |
| [AC-16](#ac-16) |          |            |    ●     |           |     ●      |           |           |             |         |
| [AC-17](#ac-17) |          |            |          |           |     ●      |           |     ○     |             |    ○    |
| [AC-18](#ac-18) |          |            |          |     ●     |            |           |     ○     |             |         |
| [AC-19](#ac-19) |          |            |          |     ●     |     ●      |     ●     |           |      ○      |         |
| [AC-20](#ac-20) |    ●     |            |          |           |            |           |           |             |    ○    |
