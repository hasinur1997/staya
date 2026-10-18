# Staya — Database Schema

Draft for review · derived from `docs/project-overview.md` and `CLAUDE.md`. No migrations exist for
these tables yet.

## Conventions

- **Primary keys:** `id` is `BIGINT UNSIGNED` auto-increment (`$table->id()`) unless stated.
- **Foreign keys:** `BIGINT UNSIGNED` (`foreignId()->constrained()`). MySQL indexes every FK
  automatically; the migrations should still add the index explicitly so SQLite/PostgreSQL match.
- **Money:** `DECIMAL(12,2)` in BDT, cast `'decimal:2'`. **Quantities:** `DECIMAL(10,3)`. No
  `FLOAT`/`DOUBLE`/`REAL` anywhere.
- **Status/type columns:** `VARCHAR` backed by a PHP enum in `app/Enums`, not a DB `ENUM`, so values
  can be added without an `ALTER` and the schema works the same on SQLite.
- **Timestamps** (`TIMESTAMP`, `*_at`) are stored in UTC.
- **Business dates** (`DATE`, `*_date`, `*_on`, `month`) are calendar dates **in Asia/Dhaka** — the
  day the user means, with no time or zone. They are what reports group by. Convert "today" to
  Asia/Dhaka before comparing or defaulting.
- **Delete behaviour:** FKs from financial records use `RESTRICT`, so nothing financial can be
  removed by a cascade. Users and members are deactivated, not deleted. `CASCADE` is used only for
  non-financial child rows.
- **Audit columns** (`created_by`, `reviewed_by`, …) point at `users.id`.
- Abbreviations below: **N** = nullable, **PK/FK/UQ/IX** = primary key / foreign key / unique / index.

## Table overview

| Table                                                                                                         | Source                               | Purpose                                         |
| ------------------------------------------------------------------------------------------------------------- | ------------------------------------ | ----------------------------------------------- |
| `users`                                                                                                       | Laravel + Fortify (exists), extended | Login accounts                                  |
| `password_reset_tokens`, `sessions`, `passkeys`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Laravel / Fortify (exist)            | Framework tables, unchanged                     |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`                    | **spatie/laravel-permission**        | Dynamic roles & permissions                     |
| `activity_log`                                                                                                | **spatie/laravel-activitylog**       | Append-only audit log                           |
| `members`                                                                                                     | App                                  | Member profiles                                 |
| `member_stays`                                                                                                | App                                  | Join/leave history                              |
| `grocery_purchases`                                                                                           | App                                  | Purchase headers                                |
| `grocery_purchase_items`                                                                                      | App                                  | Purchase line items                             |
| `deposits`                                                                                                    | App                                  | Member cash deposits                            |
| `buyer_payables`                                                                                              | App                                  | Shortfall owed to a buyer when the fund hits ৳0 |
| `month_closures`                                                                                              | App                                  | Closed/reopened reporting months                |
| `ledger_entries`                                                                                              | App                                  | Append-only fund ledger                         |
| `fund`                                                                                                        | App                                  | Single row: cached fund balance + lock target   |
| `mess_settings`                                                                                               | App                                  | Single row: mess-wide settings                  |

---

## Existing tables changed by this schema

### `users` (exists — add columns)

| Column                    | Type            | N   | Default | Notes                                                               |
| ------------------------- | --------------- | --- | ------- | ------------------------------------------------------------------- |
| id                        | BIGINT UNSIGNED |     | auto    | PK                                                                  |
| name                      | VARCHAR(255)    |     |         | Display name. For members, kept in sync with `first_name last_name` |
| email                     | VARCHAR(255)    |     |         | UQ. Login identifier                                                |
| email_verified_at         | TIMESTAMP       | N   | NULL    | Existing                                                            |
| password                  | VARCHAR(255)    |     |         | Existing. Hashed (bcrypt/argon2)                                    |
| two_factor_secret         | TEXT            | N   | NULL    | Existing (Fortify)                                                  |
| two_factor_recovery_codes | TEXT            | N   | NULL    | Existing (Fortify)                                                  |
| two_factor_confirmed_at   | TIMESTAMP       | N   | NULL    | Existing (Fortify)                                                  |
| **is_active**             | BOOLEAN         |     | `true`  | **New.** `false` blocks login; history stays                        |
| **last_login_at**         | TIMESTAMP       | N   | NULL    | **New.** Set on successful login                                    |
| remember_token            | VARCHAR(100)    | N   | NULL    | Existing                                                            |
| created_at, updated_at    | TIMESTAMP       | N   | NULL    | Existing                                                            |

Indexes: UQ `email` (exists).

The other existing framework tables are not changed.

---

## Tables from `spatie/laravel-permission`

Created by the package's published migration (`php artisan vendor:publish
--provider="Spatie\Permission\PermissionServiceProvider"`). Config: `teams => false`,
`model_morph_key => model_id`. Only users get roles; direct user permissions are not used in v1 but
the table is part of the package. Shapes below are the package defaults (v6); verify against the
published file after install.

### `permissions`

| Column                 | Type            | N   | Default | Notes                                                                       |
| ---------------------- | --------------- | --- | ------- | --------------------------------------------------------------------------- |
| id                     | BIGINT UNSIGNED |     | auto    | PK                                                                          |
| name                   | VARCHAR(255)    |     |         | `resource.action`, e.g. `deposit.approve`                                   |
| guard_name             | VARCHAR(255)    |     |         | Always `web`                                                                |
| **group**              | VARCHAR(50)     |     |         | **App addition.** Catalog resource, e.g. `deposit` (for the role editor UI) |
| **description**        | VARCHAR(255)    | N   | NULL    | **App addition.** Human-readable label                                      |
| created_at, updated_at | TIMESTAMP       | N   | NULL    |                                                                             |

Indexes: UQ (`name`, `guard_name`); IX `group` (app addition).

Rows are seeded from the permission catalog; the UI never creates or deletes permissions.

### `roles`

| Column                 | Type            | N   | Default | Notes                                                                                                                                 |
| ---------------------- | --------------- | --- | ------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| id                     | BIGINT UNSIGNED |     | auto    | PK                                                                                                                                    |
| name                   | VARCHAR(255)    |     |         | Display name (`Admin`, `Manager`, `Member`, custom)                                                                                   |
| guard_name             | VARCHAR(255)    |     |         | Always `web`                                                                                                                          |
| **is_system**          | BOOLEAN         |     | `false` | **App addition.** `true` for the three seeded roles: cannot be deleted or renamed. Admin also cannot lose role-management permissions |
| created_at, updated_at | TIMESTAMP       | N   | NULL    |                                                                                                                                       |

Indexes: UQ (`name`, `guard_name`).

Code looks up the seeded roles by name (e.g. assigning `Member` on registration approval), which is
why system role names are locked.

### `model_has_roles`

| Column     | Type            | N   | Default | Notes                                     |
| ---------- | --------------- | --- | ------- | ----------------------------------------- |
| role_id    | BIGINT UNSIGNED |     |         | FK → `roles.id` ON DELETE CASCADE         |
| model_type | VARCHAR(255)    |     |         | `App\Models\User` (or morph alias `user`) |
| model_id   | BIGINT UNSIGNED |     |         | The user id                               |

Indexes: PK (`role_id`, `model_id`, `model_type`); IX (`model_id`, `model_type`).

"A role in use cannot be deleted" is enforced in the policy/action, not by the cascade.

### `model_has_permissions`

| Column        | Type            | N   | Default | Notes                                   |
| ------------- | --------------- | --- | ------- | --------------------------------------- |
| permission_id | BIGINT UNSIGNED |     |         | FK → `permissions.id` ON DELETE CASCADE |
| model_type    | VARCHAR(255)    |     |         |                                         |
| model_id      | BIGINT UNSIGNED |     |         |                                         |

Indexes: PK (`permission_id`, `model_id`, `model_type`); IX (`model_id`, `model_type`).

### `role_has_permissions`

| Column        | Type            | N   | Default | Notes                                   |
| ------------- | --------------- | --- | ------- | --------------------------------------- |
| permission_id | BIGINT UNSIGNED |     |         | FK → `permissions.id` ON DELETE CASCADE |
| role_id       | BIGINT UNSIGNED |     |         | FK → `roles.id` ON DELETE CASCADE       |

Indexes: PK (`permission_id`, `role_id`).

The permission cache uses the cache store, so there is no extra table.

---

## Table from `spatie/laravel-activitylog`

### `activity_log`

Created by the package's published migrations (create table, then `event` and `batch_uuid`). Shapes
below are the package defaults (v4); verify against the published files after install.

| Column                 | Type            | N   | Default | Notes                                                                                                                  |
| ---------------------- | --------------- | --- | ------- | ---------------------------------------------------------------------------------------------------------------------- |
| id                     | BIGINT UNSIGNED |     | auto    | PK                                                                                                                     |
| log_name               | VARCHAR(255)    | N   | NULL    | e.g. `grocery`, `deposit`, `ledger`, `member`, `auth`, `access`, `settings`                                            |
| description            | TEXT            |     |         | Action, e.g. `approved`, `voided`, `nid_viewed`, `login_failed`                                                        |
| subject_type           | VARCHAR(255)    | N   | NULL    | Entity type (morph)                                                                                                    |
| subject_id             | BIGINT UNSIGNED | N   | NULL    | Entity id                                                                                                              |
| event                  | VARCHAR(255)    | N   | NULL    | `created` / `updated` / `deleted` or custom                                                                            |
| causer_type            | VARCHAR(255)    | N   | NULL    | Actor type; NULL for failed logins                                                                                     |
| causer_id              | BIGINT UNSIGNED | N   | NULL    | Actor (user) id                                                                                                        |
| properties             | JSON            | N   | NULL    | `{"old": {...}, "attributes": {...}}` for before/after; extra context such as `self_approved`, `reason`, `email_tried` |
| batch_uuid             | CHAR(36)        | N   | NULL    | Groups entries from one transaction                                                                                    |
| **ip_address**         | VARCHAR(45)     | N   | NULL    | **App addition** (own migration). Filled via a `tapActivity` hook / activity model so every row carries it             |
| created_at, updated_at | TIMESTAMP       | N   | NULL    | `updated_at` never changes after insert                                                                                |

Indexes: IX `log_name`; IX (`subject_type`, `subject_id`); IX (`causer_type`, `causer_id`);
IX `created_at` (app addition, for the audit list).

Append-only: the custom `Activity` model throws on update/delete, and `activitylog:clean` is never
scheduled.

---

## App tables

### `members`

| Column                 | Type            | N   | Default     | Notes                                                                         |
| ---------------------- | --------------- | --- | ----------- | ----------------------------------------------------------------------------- |
| id                     | BIGINT UNSIGNED |     | auto        | PK                                                                            |
| user_id                | BIGINT UNSIGNED |     |             | FK → `users.id` ON DELETE RESTRICT. UQ (one user per member)                  |
| first_name             | VARCHAR(100)    |     |             |                                                                               |
| last_name              | VARCHAR(100)    |     |             |                                                                               |
| phone                  | VARCHAR(20)     |     |             | Normalised Bangladeshi mobile, e.g. `+8801XXXXXXXXX`                          |
| nid_number             | VARCHAR(17)     |     |             | UQ. 10, 13 or 17 digits. Needs `member.view_nid`                              |
| father_name            | VARCHAR(150)    |     |             |                                                                               |
| mother_name            | VARCHAR(150)    |     |             |                                                                               |
| permanent_address      | TEXT            |     |             |                                                                               |
| nid_file_path          | VARCHAR(255)    |     |             | Path on the private disk. Needs `member.view_nid`                             |
| photo_path             | VARCHAR(255)    | N   | NULL        | Private disk                                                                  |
| status                 | VARCHAR(16)     |     | `'pending'` | `MemberStatus`: pending / rejected / active / inactive                        |
| rejection_reason       | TEXT            | N   | NULL        | Shown to the applicant                                                        |
| reviewed_by            | BIGINT UNSIGNED | N   | NULL        | FK → `users.id` ON DELETE RESTRICT. Who approved or rejected the registration |
| reviewed_at            | TIMESTAMP       | N   | NULL        |                                                                               |
| created_at, updated_at | TIMESTAMP       | N   | NULL        |                                                                               |

Indexes: UQ `user_id`; UQ `nid_number`; IX `status`; IX `reviewed_by`.

Email lives on `users.email` only (see _Changes from the overview_). Hard delete is allowed only when
the member has no `grocery_purchases`, `deposits` or `buyer_payables` rows; the RESTRICT FKs on those
tables back this up.

### `member_stays`

| Column                 | Type            | N   | Default | Notes                                      |
| ---------------------- | --------------- | --- | ------- | ------------------------------------------ |
| id                     | BIGINT UNSIGNED |     | auto    | PK                                         |
| member_id              | BIGINT UNSIGNED |     |         | FK → `members.id` ON DELETE CASCADE        |
| joined_on              | DATE            |     |         | Asia/Dhaka date                            |
| left_on                | DATE            | N   | NULL    | NULL = current stay. Must be ≥ `joined_on` |
| created_at, updated_at | TIMESTAMP       | N   | NULL    |                                            |

Indexes: IX (`member_id`, `joined_on`); IX (`joined_on`, `left_on`) for "active in month" queries.

Rules enforced in actions (not portable as DB constraints): at most one open stay per member; stays
of one member never overlap; `left_on ≥ joined_on`; past stays are never edited. Active in month M
⇔ `joined_on ≤ last day of M AND (left_on IS NULL OR left_on ≥ first day of M)`.

### `grocery_purchases`

| Column          | Type            | N   | Default    | Notes                                                                |
| --------------- | --------------- | --- | ---------- | -------------------------------------------------------------------- |
| id              | BIGINT UNSIGNED |     | auto       | PK                                                                   |
| purchase_date   | DATE            |     |            | Asia/Dhaka. Not future, not before fund start, not in a closed month |
| buyer_member_id | BIGINT UNSIGNED |     |            | FK → `members.id` ON DELETE RESTRICT                                 |
| grand_total     | DECIMAL(12,2)   |     |            | Server-computed sum of `line_total`. > 0                             |
| notes           | TEXT            | N   | NULL       |                                                                      |
| receipt_path    | VARCHAR(255)    | N   | NULL       | Private disk                                                         |
| status          | VARCHAR(16)     |     | `'active'` | `GroceryPurchaseStatus`: active / voided                             |
| void_reason     | TEXT            | N   | NULL       | Required when voided; `Deleted by owner` for owner voids             |
| voided_by       | BIGINT UNSIGNED | N   | NULL       | FK → `users.id` ON DELETE RESTRICT                                   |
| voided_at       | TIMESTAMP       | N   | NULL       |                                                                      |
| created_by      | BIGINT UNSIGNED |     |            | FK → `users.id` ON DELETE RESTRICT. Owner for the edit window        |
| updated_by      | BIGINT UNSIGNED | N   | NULL       | FK → `users.id` ON DELETE RESTRICT                                   |
| created_at      | TIMESTAMP       | N   | NULL       | Server time; the 30-minute edit window counts from here              |
| updated_at      | TIMESTAMP       | N   | NULL       |                                                                      |

Indexes: IX (`status`, `purchase_date`) — lists and monthly totals always filter by both;
IX `purchase_date`; IX (`buyer_member_id`, `purchase_date`); IX `created_by`; IX `updated_by`;
IX `voided_by`.

No `deleted_at`: a void is `status = voided`, never Laravel's `SoftDeletes`.

### `grocery_purchase_items`

| Column                 | Type              | N   | Default | Notes                                                                        |
| ---------------------- | ----------------- | --- | ------- | ---------------------------------------------------------------------------- |
| id                     | BIGINT UNSIGNED   |     | auto    | PK                                                                           |
| grocery_purchase_id    | BIGINT UNSIGNED   |     |         | FK → `grocery_purchases.id` ON DELETE CASCADE                                |
| item_name              | VARCHAR(100)      |     |         | Free text, trimmed                                                           |
| quantity               | DECIMAL(10,3)     |     |         | > 0                                                                          |
| unit                   | VARCHAR(10)       |     |         | `GroceryUnit`: kg / gm / liter / ml / pcs / dozen                            |
| unit_price             | DECIMAL(12,2)     |     |         | > 0                                                                          |
| line_total             | DECIMAL(12,2)     |     |         | Server-computed `quantity × unit_price`, bcmath, rounded half-up to 2 places |
| position               | SMALLINT UNSIGNED |     | `0`     | Display order                                                                |
| created_at, updated_at | TIMESTAMP         | N   | NULL    |                                                                              |

Indexes: IX (`grocery_purchase_id`, `position`); IX `item_name` (autocomplete prefix search).

The cascade never fires in practice, since purchases are never hard-deleted. On edit, the items are
replaced inside the same transaction; the activity log keeps the old item list.

### `deposits`

| Column           | Type            | N   | Default     | Notes                                                                            |
| ---------------- | --------------- | --- | ----------- | -------------------------------------------------------------------------------- |
| id               | BIGINT UNSIGNED |     | auto        | PK                                                                               |
| member_id        | BIGINT UNSIGNED |     |             | FK → `members.id` ON DELETE RESTRICT                                             |
| amount           | DECIMAL(12,2)   |     |             | > 0                                                                              |
| deposit_date     | DATE            |     |             | Asia/Dhaka. Not future, not before fund start, not in a closed month             |
| method           | VARCHAR(16)     |     | `'cash'`    | `DepositMethod`: cash (only value in v1)                                         |
| status           | VARCHAR(16)     |     | `'pending'` | `DepositStatus`: pending / approved / rejected / cancelled / reversed            |
| submitted_by     | BIGINT UNSIGNED |     |             | FK → `users.id` ON DELETE RESTRICT. Member or staff who recorded it              |
| reviewed_by      | BIGINT UNSIGNED | N   | NULL        | FK → `users.id` ON DELETE RESTRICT. Approver or rejecter                         |
| reviewed_at      | TIMESTAMP       | N   | NULL        |                                                                                  |
| rejection_reason | TEXT            | N   | NULL        | Required when rejected                                                           |
| is_self_approved | BOOLEAN         |     | `false`     | `true` when the reviewer is the deposit's own member (via `deposit.approve_own`) |
| cancelled_at     | TIMESTAMP       | N   | NULL        | Set when the member cancels while pending                                        |
| reversed_by      | BIGINT UNSIGNED | N   | NULL        | FK → `users.id` ON DELETE RESTRICT                                               |
| reversed_at      | TIMESTAMP       | N   | NULL        |                                                                                  |
| reversal_reason  | TEXT            | N   | NULL        | Required when reversed                                                           |
| created_at       | TIMESTAMP       | N   | NULL        | "Submitted at"                                                                   |
| updated_at       | TIMESTAMP       | N   | NULL        |                                                                                  |

Indexes: IX (`member_id`, `status`); IX `deposit_date`; IX (`status`, `deposit_date`) for monthly
approved totals and the pending/rejected summary; IX `submitted_by`; IX `reviewed_by`;
IX `reversed_by`; IX `is_self_approved` (report flag).

Approval: `SELECT … FOR UPDATE` on the deposit row, check `status = pending`, then update and post
the ledger entry in one transaction.

### `buyer_payables`

| Column                 | Type            | N   | Default | Notes                                                                                            |
| ---------------------- | --------------- | --- | ------- | ------------------------------------------------------------------------------------------------ |
| id                     | BIGINT UNSIGNED |     | auto    | PK                                                                                               |
| grocery_purchase_id    | BIGINT UNSIGNED |     |         | FK → `grocery_purchases.id` ON DELETE RESTRICT. UQ                                               |
| member_id              | BIGINT UNSIGNED |     |         | FK → `members.id` ON DELETE RESTRICT. The buyer (copied from the purchase for dashboard queries) |
| amount                 | DECIMAL(12,2)   |     |         | Shortfall owed. Goes down when the purchase is edited down or voided                             |
| amount_reimbursed      | DECIMAL(12,2)   |     | `0.00`  | Sum of reimbursement ledger entries for this payable. `0 ≤ amount_reimbursed ≤ amount`           |
| created_at, updated_at | TIMESTAMP       | N   | NULL    |                                                                                                  |

Indexes: UQ `grocery_purchase_id`; IX `member_id`.

One payable per purchase; edits update this row (audit-logged) rather than adding rows. "Open"
means `amount_reimbursed < amount`; this is derived, not stored. Each reimbursement is a
`ledger_entries` row with `type = reimbursement` and source = this payable, so reimbursements in a
period come from the ledger.

### `month_closures`

| Column                 | Type            | N   | Default | Notes                                                  |
| ---------------------- | --------------- | --- | ------- | ------------------------------------------------------ |
| id                     | BIGINT UNSIGNED |     | auto    | PK                                                     |
| month                  | DATE            |     |         | First day of the month (Asia/Dhaka), e.g. `2026-10-01` |
| closed_by              | BIGINT UNSIGNED |     |         | FK → `users.id` ON DELETE RESTRICT                     |
| closed_at              | TIMESTAMP       |     |         |                                                        |
| reopened_by            | BIGINT UNSIGNED | N   | NULL    | FK → `users.id` ON DELETE RESTRICT                     |
| reopened_at            | TIMESTAMP       | N   | NULL    |                                                        |
| reopen_reason          | TEXT            | N   | NULL    | Required when reopened                                 |
| created_at, updated_at | TIMESTAMP       | N   | NULL    |                                                        |

Indexes: UQ `month`; IX `closed_by`; IX `reopened_by`.

A month is closed when a row exists with `reopened_at IS NULL`. Closing again after a reopen
updates the same row (new `closed_*`, `reopened_*` cleared). The full close/reopen history lives in
`activity_log`.

### `ledger_entries` (append-only)

| Column            | Type            | N   | Default           | Notes                                                                                                                                                                    |
| ----------------- | --------------- | --- | ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| id                | BIGINT UNSIGNED |     | auto              | PK                                                                                                                                                                       |
| entry_date        | DATE            |     |                   | Asia/Dhaka. The source's own date (purchase/deposit date; a reversal uses the reversed entry's date); the day of payment for reimbursements; chosen date for adjustments |
| type              | VARCHAR(32)     |     |                   | `LedgerEntryType`: opening_balance / deposit / grocery_expense / reversal / adjustment / reimbursement                                                                   |
| direction         | VARCHAR(6)      |     |                   | `LedgerDirection`: credit (+fund) / debit (−fund)                                                                                                                        |
| amount            | DECIMAL(12,2)   |     |                   | Always > 0; the sign comes from `direction`. No zero-amount entries                                                                                                      |
| source_type       | VARCHAR(255)    | N   | NULL              | Morph: `deposit`, `grocery_purchase`, `buyer_payable`. NULL for opening balance and adjustments                                                                          |
| source_id         | BIGINT UNSIGNED | N   | NULL              |                                                                                                                                                                          |
| reverses_entry_id | BIGINT UNSIGNED | N   | NULL              | FK → `ledger_entries.id` ON DELETE RESTRICT. UQ: an entry can be reversed only once                                                                                      |
| reason            | TEXT            | N   | NULL              | Required for `reversal` and `adjustment`                                                                                                                                 |
| created_by        | BIGINT UNSIGNED |     |                   | FK → `users.id` ON DELETE RESTRICT                                                                                                                                       |
| created_at        | TIMESTAMP       |     | CURRENT_TIMESTAMP | No `updated_at`                                                                                                                                                          |

Indexes: IX `entry_date`; IX (`source_type`, `source_id`); UQ `reverses_entry_id`;
IX (`type`, `entry_date`) for period summaries; IX `created_by`.

Fund balance = `SUM(amount WHERE direction = credit) − SUM(amount WHERE direction = debit)`. The
model has no `$fillable` updates, and `updating`/`deleting` events throw. A purchase whose cost
exceeds the balance posts an expense equal to the balance (skipped if the balance is ৳0) plus a
`buyer_payables` row for the rest.

### `fund` (single row)

| Column     | Type            | N   | Default | Notes                                     |
| ---------- | --------------- | --- | ------- | ----------------------------------------- |
| id         | BIGINT UNSIGNED |     | auto    | PK. Always `1`                            |
| balance    | DECIMAL(12,2)   |     | `0.00`  | Cached; always equals the ledger sum. ≥ 0 |
| updated_at | TIMESTAMP       | N   | NULL    |                                           |

Indexes: PK only.

Every action that posts to the ledger first runs `Fund::lockForUpdate()->find(1)`, checks the
floor, inserts the entries and updates `balance`, all in one transaction. This row is the single
lock that serialises balance checks. A test asserts `fund.balance = ledger sum` after each
financial action. Seeded with one row.

### `mess_settings` (single row)

| Column                      | Type              | N   | Default        | Notes                                                                                         |
| --------------------------- | ----------------- | --- | -------------- | --------------------------------------------------------------------------------------------- |
| id                          | BIGINT UNSIGNED   |     | auto           | PK. Always `1`                                                                                |
| mess_name                   | VARCHAR(150)      | N   | NULL           | Set at setup                                                                                  |
| mess_address                | TEXT              | N   | NULL           |                                                                                               |
| currency                    | CHAR(3)           |     | `'BDT'`        | Fixed in v1                                                                                   |
| timezone                    | VARCHAR(64)       |     | `'Asia/Dhaka'` | Display/business timezone (storage stays UTC)                                                 |
| opening_balance             | DECIMAL(12,2)     |     | `0.00`         | Editable only while `ledger_entries` is empty; mirrored by the `opening_balance` ledger entry |
| fund_start_date             | DATE              | N   | NULL           | No purchase/deposit may be dated earlier                                                      |
| grocery_edit_window_minutes | SMALLINT UNSIGNED |     | `30`           |                                                                                               |
| members_can_view_balance    | BOOLEAN           |     | `true`         |                                                                                               |
| max_upload_kb               | INT UNSIGNED      |     | `5120`         | 5 MB                                                                                          |
| updated_by                  | BIGINT UNSIGNED   | N   | NULL           | FK → `users.id` ON DELETE RESTRICT                                                            |
| created_at, updated_at      | TIMESTAMP         | N   | NULL           |                                                                                               |

Indexes: PK; IX `updated_by`.

Allocation method and language are fixed in v1, so they are not stored. Seeded with one row.

---

## Relationships

```
users 1 ─── 0..1 members ─── * member_stays
users * ─── * roles            (model_has_roles)        [spatie]
roles * ─── * permissions      (role_has_permissions)   [spatie]
members 1 ─── * grocery_purchases (buyer) ─── * grocery_purchase_items
grocery_purchases 1 ─── 0..1 buyer_payables ─── 1 members
members 1 ─── * deposits
ledger_entries * ─── 0..1 source: deposits | grocery_purchases | buyer_payables  (morph)
ledger_entries 0..1 ─── 0..1 ledger_entries (reverses_entry_id)
users 1 ─── * activity_log (causer)                      [spatie]
any model 1 ─── * activity_log (subject)                 [spatie]
```

Morph map (`Relation::enforceMorphMap`): `user`, `member`, `deposit`, `grocery_purchase`,
`buyer_payable`, `ledger_entry`, `month_closure`, `mess_settings`, `role`, `permission`. This keeps
class names out of `source_type`, `subject_type`, `causer_type` and `model_type`.

---

## Changes from the overview's entity table

| Overview                                                 | This schema                                 | Why                                                                                                                     |
| -------------------------------------------------------- | ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `roles`, `permissions`, `role_permissions`, `user_roles` | Spatie tables                               | `CLAUDE.md` rule 6. `roles.slug` dropped (system roles are locked by `is_system`); `permissions.key` is Spatie's `name` |
| `audit_logs`                                             | Spatie `activity_log` + `ip_address` column | `CLAUDE.md` rule 6                                                                                                      |
| `users.password_hash`                                    | `users.password`                            | Laravel/Fortify convention                                                                                              |
| `users.phone`                                            | dropped                                     | Phone lives on `members`. Staff-only accounts don't need one in v1                                                      |
| `members.email`                                          | dropped                                     | Every member has a user, so `users.email` is the single copy and can't drift                                            |
| `members.approved_by`, `approved_at`                     | `reviewed_by`, `reviewed_at`                | Also covers rejections                                                                                                  |
| `grocery_purchases`                                      | + `voided_by`, `voided_at`                  | Who voided and when, for quick display (the audit log has the full record)                                              |
| `grocery_purchase_items`                                 | + `position`, timestamps                    | Stable line order                                                                                                       |
| `deposits`                                               | + `cancelled_at`                            | Cancellation time                                                                                                       |
| `mess_settings` key/value                                | Single typed row                            | Opening balance stays `DECIMAL(12,2)`, and types are enforced by the DB                                                 |
| —                                                        | `fund` table                                | A row to `lockForUpdate()` and to hold the cached balance the overview allows                                           |
| Index `deposits.deposit_date`                            | + (`status`, `deposit_date`)                | Monthly approved-deposit totals                                                                                         |
| Index `grocery_purchases.purchase_date`                  | + (`status`, `purchase_date`)               | Lists and totals exclude voided rows                                                                                    |

## Open questions for review

1. **NID encryption at rest.** Encrypting `nid_number` (Laravel `encrypted` cast) would break the
   unique index. If you want it encrypted, add a `nid_number_hash` (HMAC) column carrying the
   unique index instead.
2. **Phone uniqueness.** Not required by the overview. Should two members be allowed the same phone?
3. **Member email.** Is dropping `members.email` in favour of `users.email` acceptable, or does a
   member need a contact email separate from their login?
4. **Item names.** Autocomplete reads `DISTINCT item_name` from `grocery_purchase_items`. That's
   fine at mess scale; a separate `item_names` table would only be needed if this gets slow.
5. **Spatie versions.** _Resolved in Phase 1._ Installed `spatie/laravel-permission` v8 and
   `spatie/laravel-activitylog` v5 (needs PHP 8.4). The permission tables match the shapes above.
   Activitylog v5 differs from the v4 shape described here: there is no `batch_uuid` (group rows
   with a `properties` key if needed), and before/after values live in a separate
   `attribute_changes` JSON column (`{"old": …, "attributes": …}`), leaving `properties` for
   extra context such as `email_tried`.
