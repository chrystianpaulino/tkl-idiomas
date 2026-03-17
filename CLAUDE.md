# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development (runs server + queue + logs + Vite watch concurrently)
composer run dev

# Individual processes
php artisan serve
npm run dev
npm run build

# Database
php artisan migrate
php artisan migrate:fresh --seed   # Reset + seed (admin@tkl.com / password)

# Tests
php artisan test                                    # All tests
php artisan test tests/Unit/Models/ --verbose       # Unit tests only
php artisan test --filter LessonPackageTest         # Single test class
php artisan test --filter "test_scope_active_*"     # Single test method

# Code style (Laravel Pint)
./vendor/bin/pint
./vendor/bin/pint --test   # Check without fixing
```

## Architecture

### Request Flow

HTTP request → Route middleware (`auth`, `verified`, `role:admin`) → FormRequest (`authorize()` + `rules()`) → Controller (thin) → Action class → Model → Inertia response.

Controllers do no business logic — they validate via FormRequest, call one Action, flash a message, and redirect or return Inertia.

### Multi-Tenancy (in progress)

The system is being migrated to a multi-tenant SaaS. The data foundation is complete — Global Scopes, middleware, and policy enforcement are the next steps.

**Current state:**
- `schools` table exists with `id`, `name`, `slug` (unique), `email`, `active`
- All tenant-scoped tables now have a NOT NULL `school_id` FK: `users` (nullable — super_admin has no school), `classes`, `lesson_packages`, `lessons`, `materials`, `payments`
- Existing data was migrated to the default school (name: "TKL Idiomas", slug: "tkl", id: 1)
- Global Scopes are **NOT yet active** — all queries still return data across all schools

**Planned roles (not yet implemented):** `super_admin` (platform), `school_admin`, `professor`, `aluno`

**Next steps:** `BelongsToSchool` trait + `SchoolScope`, `SetTenantContext` middleware, role rename migration, school onboarding flow.

---

### Role System

Three roles: `admin`, `professor`, `aluno`. The `role` column is **intentionally excluded from `$fillable`** on `User` to prevent privilege escalation. Roles are set via direct attribute assignment in `CreateUserAction`.

Role enforcement is layered:
1. `EnsureUserHasRole` middleware on route groups (abort 403)
2. `authorize()` inside FormRequests (e.g. `StoreLessonRequest`)
3. `$this->authorize()` inside Controllers via Policies (`ClassPolicy`, `LessonPolicy`, `MaterialPolicy`, `ExerciseListPolicy`, …)

Register new route middleware aliases in `bootstrap/app.php` → `$middleware->alias()`.

**Policy registration is manual** — auto-discovery is NOT active. Every new policy must be explicitly registered in `AppServiceProvider::boot()` via `Gate::policy(Model::class, Policy::class)`. Forgetting this causes `InvalidArgumentException` at runtime on any `authorize()` or `can()` call involving that model.

### Action Classes

All business logic lives in `app/Actions/`. Subdirectories: `Classes/`, `ExerciseLists/`, `Lessons/`, `Materials/`, `Packages/`, `Payments/`, `Schedules/`, `Schools/`, `Users/`.

**Critical action — `RegisterLessonAction`:** Uses a DB transaction + `lockForUpdate()` on the `LessonPackage` to atomically increment `used_lessons` and create the lesson. Never bypass this with direct `increment()` calls outside the action.

`used_lessons` on `LessonPackage` is also excluded from `$fillable`. It is only ever modified by `RegisterLessonAction` (increment) and `DeleteLessonAction` (decrement).

**`RegisterPaymentAction::execute()`** takes 4 parameters: `User $student`, `LessonPackage $package`, `array $data`, `int $registeredBy`. The caller (controller) must pass `$request->user()->id` explicitly — the action does NOT call `Auth::id()` internally to avoid null FK issues in non-HTTP contexts.

**`GetRevenueReportAction::execute(?int $schoolId)`** returns aggregate financial data. Always pass `auth()->user()->school_id` from the controller to enforce tenant isolation. Calling without `$schoolId` returns cross-school data (super-admin use only).

**`GetDashboardStatsAction`** dispatches to `adminStats()`, `professorStats()`, or `alunoStats()` based on role. `professorStats()` returns a `classes` array (not `total_classes`). `alunoStats()` includes `payment_history` and `progress` (from `GetProgressStatsAction`).

**`scopeNeedingPayment()` on `LessonPackage`** — finds packages with a price set AND no payment, regardless of exhaustion status. (Previously had an incorrect `used_lessons >= total_lessons` condition that excluded active unpaid packages — this was fixed.)

### Models

| Model | Table | Notes |
|---|---|---|
| `User` | `users` | `isAdmin()`, `isProfessor()`, `isAluno()` helpers; `remaining_lessons` accessor sums active packages; belongs to `School` via `school_id` (nullable — super_admin will have no school) |
| `School` | `schools` | Multi-tenancy root; `slug` (unique, future subdomain), `email`, `active`; has many `User`s; default school id=1 slug="tkl" |
| `TurmaClass` | `classes` | Named `TurmaClass` because `class` is a PHP reserved word; `$table = 'classes'`; has `school_id` NOT NULL |
| `LessonPackage` | `lesson_packages` | `scopeActive()` = not exhausted AND not expired (null expires_at = never expires); has `school_id` NOT NULL |
| `Lesson` | `lessons` | `package_id` uses `restrictOnDelete()` — lessons are audit records and must not be destroyed when a package is deleted; has `school_id` NOT NULL |
| `Material` | `materials` | `download_url` accessor via `Storage::disk('public')`; has `school_id` NOT NULL |
| `Payment` | `payments` | Links `student_id` + `lesson_package_id`; fields: `amount`, `currency`, `method`, `paid_at`, `notes`; registered via `RegisterPaymentAction`; has `school_id` NOT NULL — set automatically from `$package->school_id`; unique constraint on `lesson_package_id` (one payment per package); `isPaid()` uses `exists()` — call `$pkg->payment !== null` instead when `payment` is already eager-loaded |
| `Schedule` | `schedules` | Recurring rule: `class_id`, `weekday` (0=Sun), `start_time`, `duration_minutes`, `active`; `weekdayName()` returns PT-BR day name |
| `ScheduledLesson` | `scheduled_lessons` | Concrete lesson instance from a `Schedule`; `status`: `scheduled`/`confirmed`/`cancelled`; `lesson_id` set when confirmed; managed by `CreateScheduleAction`, `GenerateScheduledLessonsAction`, `ConfirmScheduledLessonAction`, `CancelScheduledLessonAction` |
| `ExerciseList` | `exercise_lists` | Homework list assigned to a class (and optionally a lesson); `due_date` cast as `date`; `isOverdue()` uses `lt(today())` not `isPast()` (boundary: due today = not yet overdue) |
| `Exercise` | `exercises` | Individual question in an `ExerciseList`; `type`: `text` or `file`; ordered by `order` column |
| `ExerciseSubmission` | `exercise_submissions` | One per student per list; unique on `(exercise_list_id, student_id)`; `submitted_at` set only on **first** submission — never overwritten on re-submit |
| `ExerciseAnswer` | `exercise_answers` | Student answer for one exercise within a submission; `file_url` appended accessor via `Storage::disk('public')` |

`LessonPackage::scopeActive()` is used in `RegisterLessonAction` and `User::remaining_lessons` — any change here affects core billing logic.

### Payment Module

**Routes (all under `/admin` prefix, `admin.` name prefix, `role:admin` middleware):**
- `GET /admin/users/{student}/payments` → `admin.payments.index` — packages + payment status for a student
- `POST /admin/users/{student}/packages/{package}/payments` → `admin.payments.store` — register a payment
- `GET /admin/payments/report` → `admin.payments.report` — aggregate revenue dashboard

**Cross-tenant guard:** Both `index()` and `store()` verify `$student->school_id === auth()->user()->school_id` before proceeding (abort 403 on mismatch).

**Pages:**
- `resources/js/Pages/Payments/Index.jsx` — per-student package/payment table with inline payment form
- `resources/js/Pages/Admin/PaymentReport.jsx` — revenue dashboard: 4 stat cards, bar chart (plain divs), method breakdown, recent payments table

**Currency validation:** `StorePaymentRequest` enforces `in:BRL,USD,EUR` + `regex:/^[A-Z]{3}$/`.

---

### Inertia Shared Props

`HandleInertiaRequests` shares to all pages:
- `auth.user` → `{ id, name, email, role }` (or `null`)
- `auth.school` → `{ id, name, slug }` (or `null`)
- `app_name` → value of `config('app.name')` (currently "EduGest")
- `flash.success` / `flash.error` → lazy closures from session

Flash messages are shown automatically by `AppLayout`. Set them in controllers via `session()->flash('success', '...')`.

### Frontend Layout

Pages use one of two layouts:
- `GuestLayout` — login/register/password reset
- `AppLayout` — all authenticated pages (includes `Sidebar` + `TopNav`)

`AppLayout` receives an optional `title` prop for the page `<head>` tag.

### Design System

Tailwind-based. Key tokens:
- Sidebar: `bg-slate-900`, accent: `indigo-600`
- Cards: `bg-white rounded-2xl border border-gray-100 shadow-sm`
- Primary button: `bg-indigo-600 hover:bg-indigo-700 rounded-xl`
- Background: `bg-gray-50`

Reusable UI components in `resources/js/Components/`: `Avatar`, `Badge`, `DataTable`, `PageHeader`, `StatsCard`. Use these instead of reimplementing.

For icons, use inline SVG (heroicons style, 24×24 viewBox, `strokeWidth={1.5}`, `currentColor`). No icon library is installed.

### Route Organization

Routes in `routes/web.php` are organized into four comment-delimited sections:
1. **Compartilhadas** — dashboard + profile (all authenticated users)
2. **Professor + Admin** — class/lesson/material/exercise-list CRUD — registered **before** the `{class}` wildcard routes to prevent "create" matching as an ID
3. **Leitura** — read-only class/lesson/material/exercise-list routes (all authenticated)
4. **Aluno** — exercise submission routes
5. **Admin** (`/admin` prefix, `admin.` name prefix) — users, packages, enrollments, payments, schools

**Important:** `/classes/create` must remain registered before `/classes/{class}`. Maintain this ordering when adding new named routes.

### Testing

- Unit tests in `tests/Unit/Models/` (model behaviour) and `tests/Unit/Actions/` (action classes) use `RefreshDatabase` against SQLite in-memory (configured in `phpunit.xml`)
- `SchoolFactory` has `inactive()` state
- `LessonPackageFactory` has `expired()` and `exhausted()` states; `exhausted()` uses `afterCreating()` because `used_lessons` is not in `$fillable`
- `ExerciseListFactory` has `overdue()` and `noDueDate()` states; `ExerciseSubmissionFactory` has `submitted()` state
- `PaymentFactory` creates a coherent set (shared school + student + package + admin) in `definition()`. Use `forStudent(User $student)` state when you already have a student. Never call `Payment::factory()->create()` with only a partial override for `student_id` — the `lesson_package_id` will belong to the wrong student.
- All factories that create tenant-scoped models include `school_id => School::factory()` by default
- `LessonFactory` creates a shared school internally and threads `school_id` through professor, student, class, and package so all entities belong to the same school
- When testing `RegisterPaymentAction`, pass `$admin->id` as the 4th argument: `->execute($student, $package, $data, $admin->id)`
- Use `Storage::fake('public')` in tests that exercise file upload paths
- `bee:code-reviewer` agent always fails (qwen model unavailable) — skip it, use the other 4 reviewers

### PHPDoc Coverage

All backend PHP files have comprehensive PHPDocs as of 2026-03-17:
- **Models** — `@property` and `@property-read` annotations for all columns/accessors; relationship docs with business context
- **Actions** — class-level docs explaining WHY the action exists; `@param`/`@return`/`@throws` on all public methods
- **Controllers** — endpoint-level docs; cross-tenant guard notes
- **FormRequests** — authorization rules and constraint explanations
- **Policies** — per-ability docs; `before()` hook behavior documented
- **Middleware** — guard logic and abort behavior explained

When adding new code, maintain this documentation standard. Add `SUGGESTION:` inline comments for refactoring ideas rather than mixing them with functional changes.

### Database

SQLite in `.env` at `database/database.sqlite`. Tests use SQLite in-memory (already configured in `phpunit.xml` — do not comment those lines out).

Seed data: `admin@tkl.com` / `password`, 3 professors, 10 students each with a 20-lesson package, 1 class "Inglês Básico" with 5 enrolled students — all assigned to school id=1 (TKL Idiomas, slug: "tkl").

`APP_NAME=EduGest` (platform name used in shared Inertia props and on the login page). The school name "TKL Idiomas" is data, not branding — it comes from the DB via `auth.school.name`.
