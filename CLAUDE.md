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

Laravel 11 + Inertia.js + React 18. SQLite in dev and tests. No Docker required.

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

Five roles:

| Role | `school_id` | `isAdmin()` | Notes |
|---|---|---|---|
| `super_admin` | NULL | false | Platform owner; bypasses all policies via `before()` |
| `school_admin` | Required | **true** | Runs a school; full access within own school |
| `admin` | Optional | **true** | Legacy role; identical to school_admin in practice |
| `professor` | Required | false | Creates classes, registers lessons, uploads materials |
| `aluno` | Required | false | Consumes content, submits exercises |

`isAdmin()` returns `true` for both `'admin'` AND `'school_admin'` — backward compatibility.

Both `role` and `school_id` are **excluded from `User::$fillable`** to prevent mass-assignment privilege escalation. Set them only via direct attribute assignment (`$user->role = ...`, `$user->school_id = ...`) in Action classes.

**Role ceiling in `StoreUserRequest`/`UpdateUserRequest`:** `super_admin` may assign any role; `school_admin`/`admin` may only assign `professor` or `aluno`. This is enforced in `getAllowedRoles()` on both form requests.

Role middleware enforcement is layered:
1. `EnsureUserHasRole` middleware on route groups — `role:admin,school_admin,professor`
2. `authorize()` in FormRequests
3. `$this->authorize()` in Controllers via Policies

**Policy registration is MANUAL.** Auto-discovery is NOT active. Register every new policy in `AppServiceProvider::boot()` via `Gate::policy(Model::class, Policy::class)`. Forgetting this causes `InvalidArgumentException` at runtime.

Currently registered: `ClassPolicy`, `LessonPolicy`, `MaterialPolicy`, `PaymentPolicy`, `ExerciseListPolicy`.

### Action Classes

All business logic lives in `app/Actions/`. Subdirectories: `Classes/`, `ExerciseLists/`, `Lessons/`, `Materials/`, `Packages/`, `Payments/`, `Schedules/`, `Schools/`, `Users/`.

**Critical: `RegisterLessonAction`** — Uses `DB::transaction()` + `lockForUpdate()` on `LessonPackage` to atomically increment `used_lessons`. Never bypass with direct `increment()` calls. `used_lessons` is also excluded from `$fillable` — only this action (increment) and `DeleteLessonAction` (decrement) may modify it.

**`RegisterPaymentAction::execute()`** takes 4 params: `User $student`, `LessonPackage $package`, `array $data`, `int $registeredBy`. The caller (controller) must pass `$request->user()->id` explicitly — the action does NOT call `Auth::id()` internally.

**`GetRevenueReportAction::execute(?int $schoolId)`** — pass `auth()->user()->school_id` from the controller. Omitting it returns all-schools data (super_admin use only). Note: the global scope also filters when a tenant is bound, so school_admin calls are double-scoped — both are idempotent.

**`GetDashboardStatsAction`** — dispatches to `superAdminStats()`, `adminStats()`, or `professorStats()`/`alunoStats()` based on role. `adminStats()` returns `classes` array (not `total_classes`). `alunoStats()` includes `payment_history` and `progress`.

**`ProvisionSchoolAction`** — atomically creates a `School` + first `school_admin` in a single DB transaction. Not yet wired to any route; `SchoolController::store()` uses `CreateSchoolAction` (without provisioning an admin).

**`CreatePackageAction`** — passes `school_id` from `$student->school_id` into `LessonPackage::create()`. Do not remove this — the BelongsToSchool creating event only auto-assigns when no tenant context is bound (super_admin), so explicit assignment is the correct path here.

### Models

| Model | Table | BelongsToSchool | Key Notes |
|---|---|---|---|
| `User` | `users` | ✗ | `role`/`school_id` not in `$fillable`; role helpers: `isSuperAdmin()`, `isSchoolAdmin()`, `isAdmin()`, `isProfessor()`, `isAluno()`; `remaining_lessons` accessor sums active packages |
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

### Route Organization

```
GET /            → redirect to dashboard
auth + verified + tenant middleware:
  GET /dashboard                         → DashboardController
  GET|PATCH|DELETE /profile              → ProfileController

  role:admin,school_admin,professor:
    /classes/create, /classes/{id}/edit  → ClassController (write)
    /classes/{id}/lessons/create, ...    → LessonController (write)
    /classes/{id}/materials/create, ...  → MaterialController (write)
    /classes/{id}/exercise-lists/create  → ExerciseListController (write)
    ⚠ /classes/create MUST be before /classes/{class} — wildcard conflict

  All authenticated (read):
    GET /classes, /classes/{class}, /classes/{class}/lessons, ...

  role:admin,school_admin — /admin prefix — admin.* names:
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

`APP_NAME=EduGest` is the platform brand (in Inertia shared props). "TKL Idiomas" is a tenant name — comes from the DB.

---

## Known Deferred Items

These are intentional gaps acknowledged in the architecture, not bugs:

- `ProvisionSchoolAction` is wired to `SchoolController::store()` — creates School + first school_admin atomically.
- `User::$fillable` cross-school queries in `GetDashboardStatsAction::adminStats()` (student/professor counts are platform-wide for school_admin — to be addressed when User gets a scope).
