# Migrating from SQLite to MySQL

The project was originally developed against SQLite (dev + tests). It has been
ported to MySQL/MariaDB for development; tests still run on SQLite `:memory:`
via `phpunit.xml`.

This guide explains what changed, how to bring up a fresh MySQL database, and
how to verify the migration succeeded.

---

## What changed

A latent ENUM bug was fixed for the `users.role` column. The original migration
declared:

```php
$table->enum('role', ['admin', 'professor', 'aluno'])->default('aluno');
```

But the SaaS pivot introduced two new roles (`super_admin`, `school_admin`)
without expanding the schema. SQLite stores ENUM as unconstrained TEXT, so the
bug was invisible. MySQL rejects any insert/update of a value not in the enum.

Two new driver-aware migrations were added (no-op on SQLite, real DDL on
MySQL/MariaDB):

| Migration | Purpose |
|---|---|
| `2026_04_27_143000_expand_users_role_enum_for_multi_tenancy.php` | Expands enum to `('super_admin','school_admin','admin','professor','aluno')`. Runs **before** the legacy data migration. |
| `2026_04_27_143815_migrate_legacy_admin_role_to_school_admin.php` | (existing) Rewrites `admin` rows → `school_admin`. |
| `2026_04_27_143900_remove_legacy_admin_from_users_role_enum.php` | Drops `'admin'` from the enum. Final state: `('super_admin','school_admin','professor','aluno')`. Refuses to run if any row still has `role='admin'`. |

The other four enums in the schema (`lessons.status`, `payments.method`,
`scheduled_lessons.status`, `exercises.type`) were audited and match the values
the application actually uses — no changes required.

### FK drop/re-add around the school_id NOT NULL transition

Migration `2026_03_16_200001_add_school_id_to_tenant_tables.php` originally
created school_id FKs with `nullOnDelete()` (ON DELETE SET NULL).
`2026_03_16_200003_make_school_id_not_null_on_tenant_tables.php` then flips
school_id to NOT NULL via `->change()`.

MySQL rejects this combination with:

> ERROR 1830: Column 'school_id' cannot be NOT NULL: needed in a foreign key
> constraint 'classes_school_id_foreign' SET NULL

A NOT NULL column cannot be the local side of an ON DELETE SET NULL
constraint, because the SET NULL action would itself violate the NOT NULL
contract. SQLite silently allowed this — it does not validate FK actions
against column constraints — so the bug was invisible.

Two new migrations bracket the existing 200003:

| Migration | Purpose |
|---|---|
| `2026_03_16_200002_z_drop_school_id_fks_before_not_null.php` | Drops the 5 school_id FKs so 200003 can flip the columns to NOT NULL on MySQL. The `z_` suffix forces it to sort after `200002_seed_*`. |
| `2026_03_16_200004_readd_school_id_fks_after_not_null.php` | Re-adds the FKs with `restrictOnDelete()` (proper semantics for non-nullable FKs — prevents accidental cascade-deletion of tenant data). |

This is a semantic upgrade: the original SET NULL action was only meaningful
while school_id was nullable, which it no longer is. RESTRICT matches the
new contract — deleting a school with tenant data now requires explicit
cleanup or cascade.

### Migration filename ordering fix

`2026_03_12_191041_create_class_students_table.php` was renamed to
`2026_03_12_191042_create_class_students_table.php`. The original five
migrations all shared the timestamp `2026_03_12_191041`, and Laravel
sorts ties alphabetically by full filename. The string sort placed
`class_students` BEFORE `classes` (because `_` (0x5F) sorts before `e`
(0x65)), which broke the FK creation order on MySQL:

> ERROR 1824: Failed to open the referenced table 'classes'

SQLite tolerates this because it allows defining FKs to tables that don't
yet exist (lazy validation at write time). MySQL is strict at DDL time.
The rename advances `class_students` by 1 second, restoring intent.

---

## .env requirements

Minimum settings for a local MySQL/MariaDB database:

```ini
DB_CONNECTION=mysql        # or mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tkl
DB_USERNAME=root
DB_PASSWORD=root
```

The DB itself must be created with `utf8mb4` charset and a `utf8mb4_*`
collation (Laravel's default `utf8mb4_unicode_ci` is fine):

```sql
CREATE DATABASE tkl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`config/database.php` already pins `charset=utf8mb4`, `collation=utf8mb4_unicode_ci`,
`strict=true`. No code changes there.

---

## Bringing up a fresh database

```bash
# Drop & recreate (or create for the first time):
mysql -uroot -proot -e "DROP DATABASE IF EXISTS tkl; CREATE DATABASE tkl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Apply all migrations + seed test accounts in one go:
php artisan migrate:fresh --seed
```

Expected output: 24 migrations run, then the seeder creates 1 super admin,
1 school admin, 3 professors, 10 students (each with a 20-lesson package), and
1 class with 5 enrolled students.

---

## Verification

After `migrate:fresh --seed`, verify the role enum was correctly shrunk to its
final 4 values:

```sql
SHOW COLUMNS FROM users LIKE 'role';
-- Expected:
--   Type: enum('super_admin','school_admin','professor','aluno')
--   Default: aluno
```

Verify the seed data is intact:

```sql
SELECT role, COUNT(*) FROM users GROUP BY role;
-- Expected:
--   super_admin   1
--   school_admin  1
--   professor     3
--   aluno         10
```

Login at `/login` with any of the seeded accounts — password is `password` for
all of them. See `CLAUDE.md` § "Database" for the full account list.

---

## Tests still run on SQLite

`phpunit.xml` forces `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. The new
ENUM migrations are driver-aware and no-op on SQLite (where ENUM is
unconstrained TEXT anyway), so the test suite is unaffected. Run:

```bash
php artisan test
```
