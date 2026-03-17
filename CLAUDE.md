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

### Role System

Three roles: `admin`, `professor`, `aluno`. The `role` column is **intentionally excluded from `$fillable`** on `User` to prevent privilege escalation. Roles are set via direct attribute assignment in `CreateUserAction`.

Role enforcement is layered:
1. `EnsureUserHasRole` middleware on route groups (abort 403)
2. `authorize()` inside FormRequests (e.g. `StoreLessonRequest`)
3. `$this->authorize()` inside Controllers via Policies (`ClassPolicy`, `LessonPolicy`, `MaterialPolicy`, `ExerciseListPolicy`, …)

Register new route middleware aliases in `bootstrap/app.php` → `$middleware->alias()`.

**Policy registration is manual** — auto-discovery is NOT active. Every new policy must be explicitly registered in `AppServiceProvider::boot()` via `Gate::policy(Model::class, Policy::class)`. Forgetting this causes `InvalidArgumentException` at runtime on any `authorize()` or `can()` call involving that model.

### Action Classes

All business logic lives in `app/Actions/`. Subdirectories: `Classes/`, `ExerciseLists/`, `Lessons/`, `Materials/`, `Packages/`, `Payments/`, `Schedules/`, `Users/`.

**Critical action — `RegisterLessonAction`:** Uses a DB transaction + `lockForUpdate()` on the `LessonPackage` to atomically increment `used_lessons` and create the lesson. Never bypass this with direct `increment()` calls outside the action.

`used_lessons` on `LessonPackage` is also excluded from `$fillable`. It is only ever modified by `RegisterLessonAction` (increment) and `DeleteLessonAction` (decrement).

### Models

| Model | Table | Notes |
|---|---|---|
| `User` | `users` | `isAdmin()`, `isProfessor()`, `isAluno()` helpers; `remaining_lessons` accessor sums active packages; belongs to `School` via `school_id` |
| `School` | `schools` | Multi-tenancy root; `slug`, `email`, `active`; has many `User`s |
| `TurmaClass` | `classes` | Named `TurmaClass` because `class` is a PHP reserved word; `$table = 'classes'` |
| `LessonPackage` | `lesson_packages` | `scopeActive()` = not exhausted AND not expired (null expires_at = never expires) |
| `Lesson` | `lessons` | `package_id` uses `restrictOnDelete()` — lessons are audit records and must not be destroyed when a package is deleted |
| `Material` | `materials` | `download_url` accessor via `Storage::disk('public')` |
| `Payment` | `payments` | Links `student_id` + `lesson_package_id`; fields: `amount`, `currency`, `method`, `paid_at`, `notes`; registered via `RegisterPaymentAction` |
| `Schedule` | `schedules` | Recurring rule: `class_id`, `weekday` (0=Sun), `start_time`, `duration_minutes`, `active`; `weekdayName()` returns PT-BR day name |
| `ScheduledLesson` | `scheduled_lessons` | Concrete lesson instance from a `Schedule`; `status`: `scheduled`/`confirmed`/`cancelled`; `lesson_id` set when confirmed; managed by `CreateScheduleAction`, `GenerateScheduledLessonsAction`, `ConfirmScheduledLessonAction`, `CancelScheduledLessonAction` |
| `ExerciseList` | `exercise_lists` | Homework list assigned to a class (and optionally a lesson); `due_date` cast as `date`; `isOverdue()` uses `lt(today())` not `isPast()` (boundary: due today = not yet overdue) |
| `Exercise` | `exercises` | Individual question in an `ExerciseList`; `type`: `text` or `file`; ordered by `order` column |
| `ExerciseSubmission` | `exercise_submissions` | One per student per list; unique on `(exercise_list_id, student_id)`; `submitted_at` set only on **first** submission — never overwritten on re-submit |
| `ExerciseAnswer` | `exercise_answers` | Student answer for one exercise within a submission; `file_url` appended accessor via `Storage::disk('public')` |

`LessonPackage::scopeActive()` is used in `RegisterLessonAction` and `User::remaining_lessons` — any change here affects core billing logic.

### Inertia Shared Props

`HandleInertiaRequests` shares to all pages:
- `auth.user` → `{ id, name, email, role }` (or `null`)
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

Routes in `routes/web.php` use three nested groups:
1. `auth + verified` — all authenticated users (dashboard, view classes, download)
2. `role:admin,professor` — create/edit classes, register lessons, upload materials
3. `role:admin` under `/admin` — user CRUD, package management, enrollment management

**Important:** `/classes/create` is registered **before** `/classes/{class}` to prevent route conflicts. Maintain this ordering when adding new named routes.

### Testing

- Unit tests in `tests/Unit/Models/` (model behaviour) and `tests/Unit/Actions/` (action classes) use `RefreshDatabase` against SQLite in-memory (configured in `phpunit.xml`)
- `LessonPackageFactory` has `expired()` and `exhausted()` states; `exhausted()` uses `afterCreating()` because `used_lessons` is not in `$fillable`
- `ExerciseListFactory` has `overdue()` and `noDueDate()` states; `ExerciseSubmissionFactory` has `submitted()` state
- Use `Storage::fake('public')` in tests that exercise file upload paths
- `bee:code-reviewer` agent always fails (qwen model unavailable) — skip it, use the other 4 reviewers

### Database

SQLite in `.env` at `database/database.sqlite`. Tests use SQLite in-memory (already configured in `phpunit.xml` — do not comment those lines out).

Seed data: `admin@tkl.com` / `password`, 3 professors, 10 students each with a 20-lesson package, 1 class "Inglês Básico" with 5 enrolled students.
