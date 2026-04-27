# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development (server + queue + logs + Vite watch, all at once)
composer run dev

# Database
php artisan migrate
php artisan migrate:fresh --seed   # Reset + seed all test accounts

# Tests
php artisan test                                     # All tests
php artisan test tests/Unit/Models/                  # Unit model tests only
php artisan test --filter LessonPackageTest          # Single test class
php artisan test --filter "test_scope_active_*"      # Single test method

# Code style (Laravel Pint)
./vendor/bin/pint
./vendor/bin/pint --test   # Check without fixing
```

## Stack

Laravel 13 + Inertia.js + React 18. **MySQL/MariaDB in dev** (configured via `.env` — see `DB_CONNECTION`). **SQLite `:memory:` in tests** (forced by `phpunit.xml` `<env name="DB_CONNECTION" value="sqlite"/>` — do not modify). No Docker required.

Migrations that depend on engine-specific DDL (e.g. `ALTER TABLE ... MODIFY COLUMN` for ENUM changes) are driver-aware: they branch on `DB::getDriverName()` and no-op on SQLite, since SQLite stores ENUM as unconstrained TEXT. See `database/migrations/2026_04_27_143000_expand_users_role_enum_for_multi_tenancy.php` for the canonical pattern.

For first-time MySQL setup, see `docs/MYSQL_MIGRATION.md`.

---

## Architecture

### Request Flow

```
HTTP request
  → auth + verified + tenant middleware
  → EnsureUserHasRole middleware (abort 403)
  → FormRequest (authorize() + rules())
  → Controller (thin — calls one Action, flashes message, redirects or returns Inertia)
  → Action class (all business logic)
  → Model
  → Inertia response
```

Controllers do no business logic. They validate, call one Action, and respond.

### Multi-Tenancy

Tenant isolation is enforced in three layers:

**Layer 1 — `SetTenantContext` middleware** (alias: `tenant`): runs after `auth`, binds `app()->instance('tenant.school_id', $user->school_id)`. Only fires when `school_id !== null && school_id > 0`. `super_admin` users (null school_id) get no tenant context — they see all data.

**Layer 2 — `SchoolScope` global scope** (via `BelongsToSchool` trait): auto-appends `WHERE {table}.school_id = ?` to every Eloquent query when a tenant is bound. No-op in console context or for super_admin.

Applied to: `TurmaClass`, `LessonPackage`, `Lesson`, `Material`, `Payment`, `ExerciseList`, `Schedule`, `ScheduledLesson`.

**Layer 3 — Manual guards**: `User` model does NOT use `BelongsToSchool`. Any controller querying `User` must add `->where('school_id', auth()->user()->school_id)` for school_admin users. Use `->when(!auth()->user()->isSuperAdmin(), ...)` pattern.

To bypass the scope in specific queries: `Model::withoutGlobalScope(SchoolScope::class)->get()`

### Role System

Four roles:

| Role | `school_id` | `isAdmin()` | Notes |
|---|---|---|---|
| `super_admin` | NULL | false | Platform owner; bypasses all policies via `before()` |
| `school_admin` | Required | **true** | Runs a school; full access within own school. Sole admin role |
| `professor` | Required | false | Creates classes, registers lessons, uploads materials |
| `aluno` | Required | false | Consumes content, submits exercises |

`isAdmin()` returns `true` only for `'school_admin'`. The legacy `admin` role was removed in migration `2026_04_27_143815_migrate_legacy_admin_role_to_school_admin` — `isAdmin()` is now a convenience alias of `isSchoolAdmin()`.

Both `role` and `school_id` are **excluded from `User::$fillable`** to prevent mass-assignment privilege escalation. Set them only via direct attribute assignment (`$user->role = ...`, `$user->school_id = ...`) in Action classes.

**Public self-registration is DISABLED** (Wave 8 / Fix C1). The `/register` GET and POST routes were removed entirely along with `RegisteredUserController` and `Auth/Register.jsx`. New users are created exclusively via `POST /admin/users` (school_admin → `InviteUserAction`) or `ProvisionSchoolAction` (super_admin, on tenant creation). `route('register')` no longer resolves and any caller referencing it will throw `RouteNotFoundException`. See `tests/Feature/Auth/RegistrationDisabledTest.php` for the regression contract.

**Invite flow** (Wave 9). `User` implements `MustVerifyEmail`, so the `verified` middleware now actually blocks unverified accounts. Admins do **not** type a password when creating users — `POST /admin/users` collects only name, email, optional phone, and role. `InviteUserAction` creates the user in pending state (random throw-away password, `invite_token` stored as SHA-256 hash, `invite_sent_at = now()`, `email_verified_at = null`) and dispatches `UserInviteMail` via Brevo SMTP. The recipient clicks `GET /invite/{token}`; `AcceptInviteController` re-hashes the token, verifies it has not been used and is within the 7-day TTL, then `POST /invite/{token}` (validated by `AcceptInviteRequest` with `Password::defaults()`) sets the password, calls `markEmailAsVerified()`, clears `invite_token`, stamps `invite_accepted_at`, and logs the user in. Audit events: `user.invited`, `user.invite_accepted`, `user.invite_resent`. The plain token NEVER reaches the database or audit log — only the hash is persisted, and the plain value is discoverable only by whoever opens the email. Failed lookups (invalid / expired / already-used) all render the same `Auth/InviteExpired` page so attackers cannot distinguish the failure mode. Resend is exposed at `POST /admin/users/{user}/invite/resend` (gated by `UserPolicy::resendInvite`); super_admin can resend cross-school via the explicit `role:school_admin,super_admin` middleware override on that single route. See `tests/Feature/Auth/InviteFlowTest.php`. Seed accounts (`super@`, `admin@`, professors, students) get `email_verified_at = now()` directly in `DatabaseSeeder` so they bypass the invite flow.

**Role ceiling in `StoreUserRequest`/`UpdateUserRequest`:** `super_admin` may assign any role; `school_admin` may only assign `professor` or `aluno`. The allow-list is computed via `UserPolicy::assignRole()` in both form requests.

Role middleware enforcement is layered:
1. `EnsureUserHasRole` middleware on route groups — `role:school_admin,professor`
2. `authorize()` in FormRequests (delegates to Policies)
3. `$this->authorize()` in Controllers via Policies

**Policy registration is MANUAL.** Auto-discovery is NOT active. Register every new policy in `AppServiceProvider::boot()` via `Gate::policy(Model::class, Policy::class)`. Forgetting this causes `InvalidArgumentException` at runtime.

Currently registered: `ClassPolicy`, `LessonPolicy`, `MaterialPolicy`, `PaymentPolicy`, `ExerciseListPolicy`, `SchedulePolicy`, `ScheduledLessonPolicy`, `UserPolicy`, `SchoolPolicy`.

`UserPolicy` and `SchoolPolicy` (Wave 8 / Fix M3) replace the previous mix of inline guards in `UserController`/`SchoolController` and ad-hoc `isAdmin()` checks in their FormRequests. Notable rules: `school_admin` may not edit other admins or `super_admin` accounts; `school_admin` may not delete itself; `school_admin` may NOT delete its own school (catastrophic cascade — reserved for `super_admin`). The global `Gate::before` super_admin bypass means policy methods only encode school_admin/professor/aluno rules.

### Action Classes

All business logic lives in `app/Actions/`. Subdirectories: `Classes/`, `ExerciseLists/`, `Lessons/`, `Materials/`, `Packages/`, `Payments/`, `Schedules/`, `Schools/`, `Users/`.

**Critical: `RegisterLessonAction`** — Uses `DB::transaction()` + `lockForUpdate()` on `LessonPackage` to atomically increment `used_lessons`. Never bypass with direct `increment()` calls. `used_lessons` is also excluded from `$fillable` — only this action (increment) and `DeleteLessonAction` (decrement) may modify it.

**`RegisterPaymentAction::execute()`** takes 4 params: `User $student`, `LessonPackage $package`, `array $data`, `int $registeredBy`. The caller (controller) must pass `$request->user()->id` explicitly — the action does NOT call `Auth::id()` internally.

**`GetRevenueReportAction::execute(?int $schoolId)`** — pass `auth()->user()->school_id` from the controller. Omitting it returns all-schools data (super_admin use only). Note: the global scope also filters when a tenant is bound, so school_admin calls are double-scoped — both are idempotent.

**`GetDashboardStatsAction`** — dispatches to `superAdminStats()`, `adminStats()`, or `professorStats()`/`alunoStats()` based on role. `adminStats()` returns `classes` array (not `total_classes`). `alunoStats()` includes `payment_history` and `progress`.

**`ProvisionSchoolAction`** — atomically creates a `School` + first `school_admin` in a single DB transaction. Wired to `SchoolController::store()` (POST `/platform/schools`). The form captures `admin_name`, `admin_email`, `admin_password` alongside the school fields.

**`CreatePackageAction`** — passes `school_id` from `$student->school_id` into `LessonPackage::create()`. Do not remove this — the BelongsToSchool creating event only auto-assigns when no tenant context is bound (super_admin), so explicit assignment is the correct path here.

**`InviteUserAction`** (Wave 9) — replaces the old `CreateUserAction`. Generates a 48-char `Str::random()` plain token, stores its SHA-256 hash in `invite_token`, fills `password` with a throw-away `Hash::make(Str::random(64))` (so the NOT NULL constraint is satisfied but no one can log in until acceptance), stamps `invite_sent_at = now()`, and dispatches `UserInviteMail` with the plain token. Audits `user.invited` (without the token). Wrapped in `DB::transaction` so a Mail dispatch failure rolls the user creation back.

**`ResendInviteAction`** (Wave 9) — generates a fresh plain token + hash, overwrites `invite_token` and `invite_sent_at`, dispatches a new `UserInviteMail`. Defensively throws `LogicException` if `invite_accepted_at` is already set (would otherwise hand the admin a back-door token to a real account); the gate `UserPolicy::resendInvite` already blocks this path but the throw turns a misconfigured policy into a loud failure.

### Models

| Model | Table | BelongsToSchool | Key Notes |
|---|---|---|---|
| `User` | `users` | ✗ | implements `MustVerifyEmail`; `role`/`school_id` not in `$fillable`; role helpers: `isSuperAdmin()`, `isSchoolAdmin()`, `isAdmin()`, `isProfessor()`, `isAluno()`; `hasPendingInvite()` checks `invite_token !== null && invite_accepted_at === null`; `invite_token` hidden via `$hidden`; `remaining_lessons` accessor sums active packages |
| `School` | `schools` | — | Tenancy root; `slug` unique; `active` flag |
| `TurmaClass` | `classes` | ✓ | Named `TurmaClass` — `class` is a PHP reserved word; `$table = 'classes'` |
| `LessonPackage` | `lesson_packages` | ✓ | `scopeActive()` = not exhausted AND not expired; `used_lessons` not in `$fillable` |
| `Lesson` | `lessons` | ✓ | `package_id` uses `restrictOnDelete()` — lessons are audit records |
| `Material` | `materials` | ✓ | `download_url` accessor via `Storage::disk('public')` |
| `Payment` | `payments` | ✓ | Unique constraint on `lesson_package_id` (one payment per package) |
| `Schedule` | `schedules` | ✗ | `weekdayName()` returns PT-BR day name |
| `ScheduledLesson` | `scheduled_lessons` | ✓ | `status`: scheduled/confirmed/cancelled |
| `ExerciseList` | `exercise_lists` | ✗ | `isOverdue()` uses `lt(today())` — due today = NOT overdue |
| `ExerciseSubmission` | `exercise_submissions` | — | `submitted_at` set only on first submission, never overwritten |

`LessonPackage::scopeActive()` is used in `RegisterLessonAction` and `User::remaining_lessons` — changes here affect billing logic.

### Audit Logging

Wave 8 / Fix M4 introduced a dedicated `audit` log channel (see `config/logging.php`) for security-relevant events. Distinct file from `laravel.log` so post-mortems can grep cleanly. Driver: `daily`, retention: 90 days (override via `LOG_AUDIT_DAYS`), path: `storage/logs/audit.log`.

Emit events via the `App\Support\Audit::log($event, $context)` helper. The helper auto-attaches `actor_id`, `actor_role`, `actor_school_id`, `ip`, `user_agent`, and `timestamp`, and **redacts** sensitive keys (`password`, `password_confirmation`, `current_password`, `remember_token`, `admin_password`, `admin_password_confirmation`) defensively even if a caller forgets to strip them.

Auth events are wired in `app/Providers/EventServiceProvider.php` (registered explicitly in `bootstrap/providers.php` since Laravel 11+ stopped auto-registering it). Inline closures listen on `Login`, `Failed`, `Logout`, and `Lockout` and call `Audit::log()`. Each closure is wrapped in a try/catch — audit failures must never break authentication.

Currently audited events:

| Event | Source |
|---|---|
| `auth.login.success` | `EventServiceProvider` listener on `Login` |
| `auth.login.failed` | `EventServiceProvider` listener on `Failed` |
| `auth.logout` | `EventServiceProvider` listener on `Logout` |
| `auth.lockout` | `EventServiceProvider` listener on `Lockout` |
| `user.created` | `UserController::store` (alongside `user.invited`) |
| `user.invited` | `InviteUserAction` (Wave 9) |
| `user.invite_accepted` | `AcceptInviteController::accept` (Wave 9) |
| `user.invite_resent` | `ResendInviteAction` (Wave 9) |
| `user.updated` | `UserController::update` (only when `getChanges()` is non-empty; `changed_fields` array) |
| `user.role_changed` | `UserController::update` (separate emission when `old_role !== new_role`) |
| `user.deleted` | `UserController::destroy` |
| `school.provisioned` | `ProvisionSchoolAction` |
| `school.updated` | `UpdateSchoolAction` |
| `school.deleted` | `SchoolController::destroy` |
| `payment.registered` | `RegisterPaymentAction` |
| `lesson.scheduled_confirmed` | `ConfirmScheduledLessonAction` |
| `lesson.scheduled_cancelled` | `CancelScheduledLessonAction` |

Tests for the audit contract live in `tests/Feature/Audit/AuditLogTest.php`. They install a `CapturingLogger` (PSR-3) as the `audit` channel via a `LogManager` wrapper — never read the physical file.

### Route Organization

```
GET /            → redirect to dashboard
auth + verified + tenant middleware:
  GET /dashboard                         → DashboardController
  GET|PATCH|DELETE /profile              → ProfileController

  role:school_admin,professor:
    /classes/create, /classes/{id}/edit  → ClassController (write)
    /classes/{id}/lessons/create, ...    → LessonController (write)
    /classes/{id}/materials/create, ...  → MaterialController (write)
    /classes/{id}/exercise-lists/create  → ExerciseListController (write)
    ⚠ /classes/create MUST be before /classes/{class} — wildcard conflict

  All authenticated (read):
    GET /classes, /classes/{class}, /classes/{class}/lessons, ...

  role:school_admin — /admin prefix — admin.* names:
    resource /admin/users
    /admin/users/{student}/packages
    /admin/classes/{class}/enroll
    /admin/payments/report
    /admin/users/{student}/payments
    /admin/users/{student}/packages/{package}/payments
    resource /admin/schools (except show)

  role:super_admin — /platform prefix — platform.* names:
    resource /platform/schools (except show)
```

**`SchoolController` authorization:** `index()`, `edit()`, `update()`, `destroy()` — school_admin sees/edits only their own school (abort 403 on mismatch). `create()`, `store()` — super_admin only.

### Payment Module

- `GET /admin/users/{student}/payments` → `admin.payments.index`
- `POST /admin/users/{student}/packages/{package}/payments` → `admin.payments.store`
- `GET /admin/payments/report` → `admin.payments.report`

`PaymentController::index()` and `store()` manually guard against cross-tenant access: `if ($student->school_id !== auth()->user()->school_id) abort(403)` — necessary because `User` is not BelongsToSchool scoped.

Currency validation in `StorePaymentRequest`: `in:BRL,USD,EUR` + `regex:/^[A-Z]{3}$/`.

### Inertia Shared Props

`HandleInertiaRequests` shares to all pages:
- `auth.user` → `{ id, name, email, role }` (or `null`)
- `auth.school` → `{ id, name, slug }` (or `null`)
- `app_name` → `config('app.name')` (currently "EduGest")
- `flash.success` / `flash.error` → lazy closures from session

Flash in controllers: `session()->flash('success', '...')`. `AppLayout` renders them automatically.

### Frontend

Two layouts: `GuestLayout` (auth pages), `AppLayout` (all authenticated pages — Sidebar + TopNav). `AppLayout` takes an optional `title` prop.

Reusable components in `resources/js/Components/`: `Avatar`, `Badge`, `DataTable`, `PageHeader`, `StatsCard`. Use these instead of reimplementing.

Icons: inline SVG (heroicons style, 24×24 viewBox, `strokeWidth={1.5}`, `currentColor`). No icon library installed.

Design tokens: Sidebar `bg-slate-900`, accent `indigo-600`, cards `bg-white rounded-2xl border border-gray-100 shadow-sm`, primary button `bg-indigo-600 hover:bg-indigo-700 rounded-xl`, background `bg-gray-50`.

---

## Testing

SQLite in-memory for all tests — configured in `phpunit.xml`, do not modify those lines.

**Factory notes:**
- `PaymentFactory` creates a coherent graph (school → student → package → admin) in `definition()`. Use `forStudent(User $student)` state when you have an existing student. Never partially override `student_id` without also overriding `lesson_package_id` — the package must belong to that student.
- `LessonPackageFactory` has `expired()` and `exhausted()` states. `exhausted()` uses `afterCreating()` because `used_lessons` is not in `$fillable`.
- `ExerciseListFactory` has `overdue()` and `noDueDate()` states.
- `ExerciseSubmissionFactory` has `submitted()` state.
- `SchoolFactory` has `inactive()` state.
- `UserFactory` has an `admin()` state.
- All factories for BelongsToSchool models include `school_id => School::factory()` by default.

**Tenant context in tests:** Tests that exercise `BelongsToSchool` scoping must call `app()->forgetInstance('tenant.school_id')` in `tearDown()` to prevent bleed between tests. Several test classes already do this — follow the pattern.

**Test suite structure:**
```
tests/Unit/Models/          Model behaviour + SchoolScope
tests/Unit/Actions/         Action classes (Lessons/, Payments/, Schools/)
tests/Unit/Middleware/      SetTenantContext unit + fuzz
tests/Unit/Chaos/           Resilience and failure mode tests
tests/Feature/Tenant/       Full HTTP lifecycle tenant isolation
tests/Feature/Auth/         Breeze auth flows
```

**Code reviewer note:** `bee:code-reviewer` (qwen model) is unavailable — skip it, use the other 4 reviewers (business-logic, security, test, nil-safety, consequences).

---

## Database

Seed accounts (password: `password` for all):
- `super@tkl.com` — `super_admin` (school_id: NULL)
- `admin@tkl.com` — `school_admin` (school id=1, TKL Idiomas)
- `ana.silva@tkl.com`, `bruno.costa@tkl.com`, `carla.mendes@tkl.com` — professors
- 10 students (`alice.ferreira@example.com` … `joao.carvalho@example.com`) — each with a 20-lesson package

Default school: id=1, name="TKL Idiomas", slug="tkl-idiomas".

Wave 9: every seed account is stamped `email_verified_at = now()` directly in `DatabaseSeeder`, so the `verified` middleware (now active because `User implements MustVerifyEmail`) does not block login on a fresh `migrate:fresh --seed`.

`APP_NAME=EduGest` is the platform brand (in Inertia shared props). "TKL Idiomas" is a tenant name — comes from the DB.

**Password policy.** `Password::defaults()` is registered in `AppServiceProvider::boot()` with: min 12 chars, mixed case, numbers, symbols. The `->uncompromised()` (haveibeenpwned) check is enabled only in production. The seeders bypass the validator (`Hash::make('password')` directly) so dev convenience accounts keep the trivial 8-char `password` — that exemption is **dev-only**. Anywhere a user-supplied password reaches a FormRequest (password reset, password update, `AcceptInviteRequest`, `StoreSchoolRequest`), the strict rule applies. `StoreUserRequest` no longer collects a password (Wave 9): the user defines theirs through the invite flow.

---

## Deployment

Production deploy checklist lives at `docs/DEPLOYMENT.md`. Highlights: copy `.env.production.example` → `.env`, fill all blanks (especially `MAIL_*` for the Brevo SMTP relay and `SESSION_SECURE_COOKIE=true`), run `migrate --force` + `storage:link` + the three `*:cache` commands, install the queue Supervisor program, and verify by triggering a real invite end-to-end.

---

## Known Deferred Items

These are intentional gaps acknowledged in the architecture, not bugs:

- `User` model has no `BelongsToSchool` scope. Cross-school queries on users must be guarded manually with `->where('school_id', auth()->user()->school_id)`. To be revisited when `User` gets its own scope.
