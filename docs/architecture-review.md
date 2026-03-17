# TKL Idiomas — Architecture Review & Proposal

> **Audience:** Lead developer with full codebase context.
> **Purpose:** Assess current design quality, identify gaps, and propose concrete next steps — not an implementation plan.
> **Date:** 2026-03-13

---

## 1. Current Architecture Strengths

- **Route → FormRequest → Controller → Action → Model** is a clean, testable pattern. Controllers stay thin by design and actions are single-responsibility — easy to unit-test without HTTP overhead.
- **`RegisterLessonAction` uses `lockForUpdate()` inside a `DB::transaction`** — the concurrency hazard of double-decrementing a package is correctly handled at the only place that matters.
- **`LessonPackage::scopeActive()`** encapsulates the two-condition active check (not exhausted AND not expired) in one place. Any query that needs active packages uses it consistently.
- **`$table = 'classes'` on `TurmaClass`** sidesteps the PHP reserved-word collision without a naming hack in the rest of the code. The model name is readable, the table name is database-safe.
- **`class_students` pivot with `BelongsToMany`** cleanly separates the group membership concept from lesson tracking — students can be enrolled without lessons being registered yet.
- **`Material` is scoped to a class**, which makes sense for shared resources (syllabi, vocabulary sheets). The separate `lesson_materials` concept (per-lesson) is not yet needed for shared assets.
- **SQLite in development** keeps the local setup frictionless. The schema is simple enough that migrating to MySQL/PostgreSQL for production is a one-line `.env` change.

---

## 2. Gap Analysis

| Requirement | Current State | Gap | Priority |
|---|---|---|---|
| **Package pricing display** ("R$220 for 4 lessons") | `lesson_packages` has no `price` column | Cannot show price to student or admin; billing conversations have no source of truth | High |
| **Payment tracking (PIX)** | No `payments` table; tracked manually | No audit trail; professor must remember who paid; no "needs to pay" alert possible | High |
| **Lesson attendance / status** | `lessons` has no `status` column | Cannot distinguish completed / cancelled / absent; all lessons look identical | High |
| **Student "needs to pay again" alert** | No active package = silent gap | Professor has no dashboard signal; student gets no warning until package is fully gone | High |
| **Upcoming / scheduled lessons** | Only `conducted_at` exists (past-only) | Cannot show a student their next lesson; no calendar view possible | High |
| **Lesson-specific materials** | `Material` is class-scoped only | Cannot attach a PDF to a specific lesson; homework uploads are impossible | Medium |
| **Group lesson registration efficiency** | `RegisterLessonAction::execute()` takes 1 student | Registering a group of 3 requires 3 separate HTTP calls; no atomic group operation | Medium |
| **Class capacity control** | No `capacity` column on `classes` | Cannot prevent a "Duo" class from growing to 5 students | Low |
| **SaaS multi-tenancy** | No `school_id` anywhere | Every table is globally scoped; adding a second school would mix all data | Medium (future) |

---

## 3. Database Schema Improvements

### A) Add `price` and `currency` to `lesson_packages`

```php
// database/migrations/YYYY_MM_DD_add_price_to_lesson_packages_table.php

public function up(): void
{
    Schema::table('lesson_packages', function (Blueprint $table) {
        $table->decimal('price', 8, 2)->nullable()->after('total_lessons');
        $table->string('currency', 3)->default('BRL')->after('price');
    });
}

public function down(): void
{
    Schema::table('lesson_packages', function (Blueprint $table) {
        $table->dropColumn(['price', 'currency']);
    });
}
```

**Design decisions:**
- `nullable` because historical packages pre-date price tracking. `null` means "price not recorded" — different from `0.00`.
- `currency` defaults to `BRL` so existing records are correctly labelled without a data migration.
- `decimal(8, 2)` supports up to R$999,999.99 — sufficient for any lesson package price.

---

### B) Add `capacity` to `classes`

```php
// database/migrations/YYYY_MM_DD_add_capacity_to_classes_table.php

public function up(): void
{
    Schema::table('classes', function (Blueprint $table) {
        $table->unsignedSmallInteger('capacity')->nullable()->after('description');
    });
}

public function down(): void
{
    Schema::table('classes', function (Blueprint $table) {
        $table->dropColumn('capacity');
    });
}
```

**Design decisions:**
- `nullable` = unlimited. Avoids retrofitting existing classes with an arbitrary number.
- `unsignedSmallInteger` is enough (max 65,535) and uses less storage than `int`.

---

### C) Add `status` and `scheduled_at` to `lessons`

```php
// database/migrations/YYYY_MM_DD_add_status_and_scheduled_at_to_lessons_table.php

public function up(): void
{
    Schema::table('lessons', function (Blueprint $table) {
        $table->enum('status', [
            'scheduled',
            'completed',
            'cancelled',
            'absent_excused',
            'absent_unexcused',
        ])->default('completed')->after('conducted_at');

        $table->timestamp('scheduled_at')->nullable()->after('status');
    });
}

public function down(): void
{
    Schema::table('lessons', function (Blueprint $table) {
        $table->dropColumn(['status', 'scheduled_at']);
    });
}
```

**Design decisions:**
- Default is `completed` so all existing lesson rows remain valid after the migration with no data backfill.
- Two absence types: `absent_excused` and `absent_unexcused`. Whether `absent_excused` consumes a package lesson is a school policy decision — the action layer must implement that rule, not the schema.
- `scheduled_at` is separate from `conducted_at`. A scheduled lesson has `scheduled_at` set and `conducted_at = null`. When confirmed, `conducted_at` is populated and `status` moves to `completed`.

---

### D) Create `lesson_materials` table

```php
// database/migrations/YYYY_MM_DD_create_lesson_materials_table.php

public function up(): void
{
    Schema::create('lesson_materials', function (Blueprint $table) {
        $table->id();
        $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
        $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
        $table->string('title');
        $table->string('file_path');
        $table->text('description')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('lesson_materials');
}
```

**Design decisions:**
- `cascadeOnDelete` on `lesson_id` — a lesson material has no meaning without the lesson. If a lesson is deleted, its materials go with it.
- `restrictOnDelete` on `uploaded_by` — you should never silently lose track of who uploaded a file; the action layer must reassign or archive before deleting a user.
- This table coexists with the existing `materials` (class-scoped). Do not rename or migrate existing rows — they serve a different purpose (shared class resources vs. lesson-specific handouts).

---

### E) Create `payments` table

```php
// database/migrations/YYYY_MM_DD_create_payments_table.php

public function up(): void
{
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
        $table->foreignId('lesson_package_id')->constrained('lesson_packages')->restrictOnDelete();
        $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
        $table->decimal('amount', 8, 2);
        $table->string('currency', 3)->default('BRL');
        $table->enum('method', ['pix', 'cash', 'card', 'transfer', 'other'])->default('pix');
        $table->timestamp('paid_at');
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->index('student_id');
        $table->index('lesson_package_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('payments');
}
```

**Design decisions:**
- `restrictOnDelete` on all three FKs — payments are financial audit records and must **never** be cascade-deleted if a user or package is removed. The action layer must handle those scenarios explicitly.
- `registered_by` distinguishes who recorded the payment (admin or professor) from who paid (student). Useful for audit and for resolving disputes.
- `paid_at` is explicit and separate from `created_at` — a payment may be recorded days after it was actually received (e.g., retroactive PIX confirmation).
- `method` defaults to `pix` since that is the current primary channel; adding new methods later is an `ALTER TABLE` on the enum.

---

### F) SaaS preparation — `schools` table + nullable `school_id` on users

```php
// database/migrations/YYYY_MM_DD_create_schools_table.php

public function up(): void
{
    Schema::create('schools', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique(); // subdomain or URL identifier
        $table->string('email')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('schools');
}
```

```php
// database/migrations/YYYY_MM_DD_add_school_id_to_users_table.php

public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->foreignId('school_id')
            ->nullable()
            ->constrained('schools')
            ->nullOnDelete()
            ->after('id');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropForeign(['school_id']);
        $table->dropColumn('school_id');
    });
}
```

**Design decisions:**
- All existing users get `school_id = null`, which means "standalone mode." No data migration required.
- `nullOnDelete` on the FK — if a school record is deleted, users are not deleted; they become unscoped. This is a safeguard, not the happy path.
- `slug` enables subdomain routing (`tklidiomas.yourapp.com`) in Phase 3 without a schema change.
- Do **not** add `school_id` to other business tables yet. That is Phase 2.

---

## 4. Model Changes

### `LessonPackage`

```php
// Add to $fillable
protected $fillable = [
    // ... existing fields ...
    'price',
    'currency',
];

// New relationship
public function payment(): HasOne
{
    return $this->hasOne(Payment::class, 'lesson_package_id');
}

// Helper
public function isPaid(): bool
{
    return $this->payment()->exists();
}

// New scope — packages that are exhausted and have no associated payment
// Used for the professor "needs to pay" alert widget
public function scopeNeedingPayment(Builder $query): void
{
    $query->whereColumn('used_lessons', '>=', 'total_lessons')
          ->whereDoesntHave('payment');
}
```

**Notes:**
- `scopeNeedingPayment` deliberately uses `>=` not `=` — a bug could push `used_lessons` above `total_lessons`; this catches that too.
- `isPaid()` is a convenience helper for view logic. Avoid calling it in loops (N+1); use `with('payment')` eager loading.

---

### `Lesson`

```php
// Add to $fillable
protected $fillable = [
    // ... existing fields ...
    'status',
    'scheduled_at',
];

// New relationship
public function lessonMaterials(): HasMany
{
    return $this->hasMany(LessonMaterial::class);
}

// Status helpers
public function isCompleted(): bool
{
    return $this->status === 'completed';
}

public function isCancelled(): bool
{
    return $this->status === 'cancelled';
}

public function isAbsent(): bool
{
    return in_array($this->status, ['absent_excused', 'absent_unexcused']);
}

// Scopes
public function scopeUpcoming(Builder $query): void
{
    $query->where('scheduled_at', '>', now())
          ->where('status', 'scheduled');
}

public function scopeCompleted(Builder $query): void
{
    $query->where('status', 'completed');
}
```

---

### `TurmaClass`

```php
// Add to $fillable
protected $fillable = [
    // ... existing fields ...
    'capacity',
];

// Capacity helpers
public function isFull(): bool
{
    if ($this->capacity === null) {
        return false;
    }
    return $this->students()->count() >= $this->capacity;
}

public function availableSlots(): ?int
{
    if ($this->capacity === null) {
        return null; // null = unlimited
    }
    return max(0, $this->capacity - $this->students()->count());
}
```

**Note:** `isFull()` and `availableSlots()` both hit the database. Cache the student count if calling from a list view.

---

### `User`

```php
// New relationship
public function payments(): HasMany
{
    return $this->hasMany(Payment::class, 'student_id');
}

// Helper — student has no active package
public function needsToRenewPackage(): bool
{
    return ! $this->lessonPackages()->active()->exists();
}
```

**Note:** `role` remains **not** in `$fillable` — correct. Role assignment must go through a dedicated action, never mass assignment.

---

## 5. New Action Classes

### A) `app/Actions/Lessons/RegisterGroupLessonAction.php`

```
Signature:
    execute(TurmaClass $class, array $students, User $professor, array $data): Collection

Pseudocode:
    DB::transaction(function () {
        foreach ($students as $student) {
            $lessons[] = RegisterLessonAction::execute($student, $professor, $data + ['class_id' => $class->id]);
        }
        return collect($lessons);
    });
```

**Design decisions:**
- Wraps individual `RegisterLessonAction` calls in a **single outer transaction** — if one student's package is exhausted, the entire group registration rolls back. This prevents partial states where some students have a lesson logged and others don't.
- Does not duplicate `lockForUpdate()` logic — delegates entirely to `RegisterLessonAction`, which already handles concurrency correctly.
- Returns a `Collection` of created `Lesson` instances so the controller can pass them to Inertia.

---

### B) `app/Actions/Lessons/CancelLessonAction.php`

```
Signature:
    execute(Lesson $lesson, bool $refundLesson = true): Lesson

Pseudocode:
    if ($refundLesson) {
        DB::transaction(function () {
            $lesson->package()->lockForUpdate()->decrement('used_lessons');
            $lesson->update(['status' => 'cancelled']);
        });
    } else {
        // absent_unexcused: package lesson is consumed, lesson stays as record
        $lesson->update(['status' => 'absent_unexcused']);
    }
    return $lesson->fresh();
```

**Design decisions:**
- The lesson record is **never deleted** — it stays as an audit entry regardless of outcome.
- `lockForUpdate()` on the package decrement mirrors the same concurrency protection as `RegisterLessonAction`. Both operations that touch `used_lessons` must lock.
- `$refundLesson = true` is the default — the safer option. Passing `false` is an explicit opt-in to the "lesson counts as used" path.
- `absent_excused` is **not handled here** because whether it refunds the lesson is school-policy-dependent. Add a second `bool $excused` parameter when that policy is defined.

---

### C) `app/Actions/Payments/RegisterPaymentAction.php`

```
Signature:
    execute(User $student, LessonPackage $package, array $data): Payment

Pseudocode:
    throw_unless($package->student_id === $student->id, InvalidArgumentException::class);

    return Payment::create([
        'student_id'        => $student->id,
        'lesson_package_id' => $package->id,
        'registered_by'     => auth()->id(),
        'amount'            => $data['amount'],
        'method'            => $data['method'] ?? 'pix',
        'paid_at'           => $data['paid_at'],
        'notes'             => $data['notes'] ?? null,
        'currency'          => $data['currency'] ?? 'BRL',
    ]);
```

**Design decisions:**
- The `$package->student_id === $student->id` guard prevents recording a payment for the wrong student — a runtime assertion, not just a form validation.
- No transaction needed — this is a single insert with no dependent state changes.
- `registered_by` is resolved from `auth()->id()` inside the action. The controller should not pass this value from the request — it must come from the authenticated session.

---

### D) `app/Actions/Lessons/ScheduleLessonAction.php`

```
Signature:
    execute(TurmaClass $class, User $student, User $professor, array $data): Lesson

Pseudocode:
    // Does NOT consume a package lesson
    return Lesson::create([
        'class_id'     => $class->id,
        'student_id'   => $student->id,
        'professor_id' => $professor->id,
        'package_id'   => $data['package_id'] ?? null,
        'title'        => $data['title'],
        'notes'        => $data['notes'] ?? null,
        'scheduled_at' => $data['scheduled_at'],
        'status'       => 'scheduled',
        // conducted_at intentionally null
    ]);
```

**Design decisions:**
- This action creates a placeholder lesson with `status = 'scheduled'`. It does **not** call `RegisterLessonAction` and does **not** touch `used_lessons`.
- When the lesson is confirmed as completed, call `RegisterLessonAction` passing this existing `Lesson` ID (or implement a `ConfirmLessonAction` that updates status + populates `conducted_at` + increments `used_lessons`).
- `package_id` is optional at scheduling time — a student may not have a package yet when a future lesson is booked.

---

## 6. Student Dashboard UX Improvements

`GetDashboardStatsAction` should return the following shape for the `aluno` role:

```php
[
    'activePackage' => [
        'id'           => 42,
        'total_lessons' => 4,
        'used_lessons'  => 3,
        'remaining'     => 1,
        'price'         => '220.00',
        'currency'      => 'BRL',
        'isPaid'        => true,
        'expires_at'    => null,
        'warning'       => 'last_lesson', // 'last_lesson' | 'expired' | 'exhausted' | null
    ],
    'recentLessons'   => [...], // last 5 completed lessons — eager load professor name + status
    'upcomingLessons' => [...], // where scheduled_at > now() and status = 'scheduled'
    'enrolledClasses' => [...],
    'stats' => [
        'totalLessonsUsed' => 3,
        'remaining'        => 1,
        'nextPaymentDue'   => true, // true when remaining <= 1
    ],
]
```

**Warning logic:**

| Condition | `warning` value |
|---|---|
| `remaining <= 1 && remaining > 0` | `'last_lesson'` |
| `used_lessons >= total_lessons` | `'exhausted'` |
| `expires_at` is past | `'expired'` |
| None of the above | `null` |

**UI guidance for the React components:**

- **Package progress bar:** `(used_lessons / total_lessons) * 100`%. Color: green < 50%, yellow >= 50%, red >= 75% or `last_lesson` warning.
- **Last lesson alert:** Prominent banner — "1 aula restante — próximo pagamento necessário" when `stats.nextPaymentDue === true`.
- **No active package banner:** "Pacote esgotado — aguardando renovação" when `activePackage === null`.
- **Lesson history row:** date badge (formatted `conducted_at`) + lesson title + professor first name + status icon. Use a simple map: `completed → ✔ green`, `cancelled → ✕ grey`, `absent_unexcused → ✕ red`, `absent_excused → ~ yellow`.
- **Upcoming lessons section:** Render only when `upcomingLessons.length > 0`. Show `scheduled_at` date + time + title. Hide entire section if the `ScheduleLessonAction` feature is not yet active.

---

## 7. Professor Workflow Improvements

### Dashboard widgets to add

- **"Alunos com pacote esgotado"** — query: `LessonPackage::needingPayment()->with('student')->get()` scoped to the professor's classes. Count badge on sidebar nav.
- **"Alunos com 1 aula restante"** — query: packages where `(total_lessons - used_lessons) = 1` and `scopeActive()`. Early warning before exhaustion.

### New views/features

| Feature | Where | Action Class |
|---|---|---|
| Bulk group lesson registration | Class detail page → "Registrar aula para turma" button | `RegisterGroupLessonAction` |
| Lesson status update (confirm / cancel) | Lesson detail page or inline on lesson list | `CancelLessonAction` |
| Schedule upcoming lesson | Class detail page → "Agendar aula" | `ScheduleLessonAction` |
| Per-lesson material upload | Lesson detail page → "Materiais desta aula" | Direct `LessonMaterial::create` or a dedicated `UploadLessonMaterialAction` |
| Record payment | Student profile page → "Registrar pagamento" | `RegisterPaymentAction` |

### Form request validations to add

- `RegisterGroupLessonRequest` — validates `student_ids` array (all must belong to the class).
- `CancelLessonRequest` — validates `refund_lesson` boolean.
- `RegisterPaymentRequest` — validates `amount > 0`, `method` in allowed enum, `paid_at` is a valid past timestamp.

---

## 8. SaaS Multi-Tenancy Preparation

### Phase 1 — Do now: nullable `school_id` on users

- Migration F (above) adds `school_id` as nullable to `users`. All existing rows get `null`.
- No middleware changes. No routing changes. No query scope changes.
- This is a zero-risk change — existing behaviour is completely unchanged.
- Benefit: the column exists in production so Phase 2 can add data without a destructive migration.

### Phase 2 — Later: enforce tenant scope on business tables

Add `school_id` to: `classes`, `lesson_packages`, `lessons`, `materials`, `payments`.

Implement a `HasSchool` trait:

```php
// app/Concerns/HasSchool.php

trait HasSchool
{
    protected static function bootHasSchool(): void
    {
        static::addGlobalScope('school', function (Builder $builder) {
            if ($schoolId = auth()->user()?->school_id) {
                $builder->where(
                    $builder->getModel()->getTable() . '.school_id',
                    $schoolId
                );
            }
        });
    }
}
```

Apply to each model with `use HasSchool;`. The global scope silently no-ops when `school_id` is null (standalone mode), so existing data is unaffected during the transition.

Add a `ResolveTenantMiddleware` that reads the subdomain from the request, finds the matching `School` by `slug`, and sets it on the authenticated user's session — so `auth()->user()->school_id` is always populated for scoped requests.

### Phase 3 — When SaaS: billing per school

```php
// Add to schools table
$table->enum('plan', ['free', 'basic', 'pro'])->default('free');

// Separate subscriptions table (Cashier or custom)
```

Gate features using `$school->plan` in middleware or via a `PlanGate` service. Do not mix plan logic into models.

**Architecture recommendation:** Use the `HasSchool` trait approach rather than Spatie Multitenancy or Tenancy for Laravel. The stack is simple; the overhead of a full multitenancy package is not justified at this scale. The trait + middleware approach is transparent, debuggable, and requires no package updates.

---

## 9. Implementation Priority

| Feature | Effort | Business Value | Priority | Phase |
|---|---|---|---|---|
| Package price display (`price` + `currency` on `lesson_packages`) | Very Low | High UX | P0 | Phase 1 |
| Lesson status (`status` column + `CancelLessonAction`) | Low | High | P0 | Phase 1 |
| Payment tracking (`payments` table + `RegisterPaymentAction`) | Low | High | P0 | Phase 1 |
| Student dashboard warnings (last lesson / exhausted alerts) | Low | High UX | P0 | Phase 1 |
| Professor "needs to pay" widget | Low | High | P0 | Phase 1 |
| Lesson scheduling — `scheduled_at` + `ScheduleLessonAction` | Medium | High | P1 | Phase 2 |
| Group lesson registration (`RegisterGroupLessonAction`) | Low | Medium | P1 | Phase 2 |
| Lesson-specific materials (`lesson_materials` table) | Medium | Medium | P1 | Phase 2 |
| Schools table + nullable `school_id` on users | Low | High (future) | P1 | Phase 2 |
| Attendance tracking (excused/unexcused absence flows) | Medium | Medium | P2 | Phase 2 |
| Class capacity (`capacity` column + `isFull()`) | Low | Low | P2 | Phase 3 |
| Full SaaS tenancy (Phase 2 scope enforcement) | High | High (future) | P3 | Phase 3 |

---

## Quick Wins — Do These First

Three changes that require minimal effort and unblock the most downstream features:

1. **Add `price`/`currency` to `lesson_packages`** — one migration, two fillable fields. Unblocks payment display and the dashboard warning.
2. **Add `status` to `lessons` + default to `completed`** — one migration, zero data migration. Unblocks `CancelLessonAction`, absence tracking, and the lesson history UI.
3. **Create `payments` table + `RegisterPaymentAction`** — one migration, one action, one form request. Unblocks the entire payment audit trail and the "needs to pay" alert.

All three can ship in a single PR and touch no existing behaviour.
