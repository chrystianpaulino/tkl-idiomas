# TKL Idiomas — Feature Proposals

> **Última atualização:** 2026-03-17

## 📊 Status das Features

| # | Feature | Status |
|---|---|---|
| 1 | Class Scheduling System | ✅ Implementado |
| 2 | Notifications | ⚠️ Tabela criada, lógica não implementada |
| 3 | WhatsApp Integration | ❌ Não iniciado |
| 4 | Student Progress Tracking | ✅ Implementado (`GetProgressStatsAction`) |
| 5 | `users.phone` column | ✅ Implementado |
| 6 | Exercise Lists / Homework | ✅ Implementado (completo) |
| — | Multi-Tenant SaaS | 🔄 Em progresso (fundação de dados completa) |
| — | School Management UI | ✅ Implementado (`/admin/schools`) |

---

*(Propostas originais abaixo — algumas já implementadas, ver status acima)*

---

# TKL Idiomas — Feature Proposals (original)

> **Audience:** Lead developer with full codebase context.
> **Purpose:** Concrete implementation proposals for the next development phases — schema, actions, routing, and integration details.
> **Date:** 2026-03-13
> **Status of already-implemented features:** `payments` table, `Payment` model, `RegisterPaymentAction`, `PaymentController`, `PaymentPolicy`, `lesson_packages.price` + `currency`, `lessons.status` + `lessons.scheduled_at`, `CancelLessonAction`, `LessonPackage::scopeNeedingPayment()`.

---

## Implementation Priority

| Feature | Effort | Business Value | Implement When |
|---|---|---|---|
| Schools table — SaaS Phase 1 | Very Low | High (future) | Now |
| `schedules` + `scheduled_lessons` tables | Low | High | Now |
| `GenerateScheduledLessonsAction` + artisan command | Medium | High | Now |
| `ConfirmScheduledLessonAction` | Low | High | Now |
| `CancelScheduledLessonAction` | Low | High | Now |
| Student progress — dynamic, no schema | Low | Medium | Now |
| Notifications — `PackageAlmostFinished`, `PackageFinished` | Low | High | Now |
| `users.phone` column for WhatsApp prep | Very Low | Low | Now |
| Notifications — `UpcomingLessonReminder` | Medium | High | After scheduling |
| WhatsApp channel implementation | High | High | Future |
| SaaS Phase 2 — `school_id` on all tables | Medium | High (future) | When needed |

---

## Feature 1 — Class Scheduling System

### Problem

Lessons are only registered after they happen. There is no way for teachers to plan recurring weekly schedules, view upcoming obligations, or give students advance notice of their next lesson.

### Design Overview

Two new tables — `schedules` (recurring weekly patterns) and `scheduled_lessons` (concrete upcoming occurrences) — sit alongside the existing `lessons` table. They are a planning layer, not a billing layer. No credits are consumed until a `ScheduledLesson` is confirmed.

The key invariant: `lesson_id` on `scheduled_lessons` is `NULL` until the lesson actually happens. At confirmation, `ConfirmScheduledLessonAction` calls the existing `RegisterLessonAction` for each enrolled student and stores the resulting `lesson_id`.

### Schema

#### Table: `schedules`

```php
// database/migrations/2026_03_14_000001_create_schedules_table.php

public function up(): void
{
    Schema::create('schedules', function (Blueprint $table) {
        $table->id();
        $table->foreignId('class_id')
              ->constrained('classes')
              ->cascadeOnDelete();
        $table->tinyInteger('weekday')->unsigned(); // 0=Sunday ... 6=Saturday
        $table->time('start_time');
        $table->smallInteger('duration_minutes')->unsigned()->default(60);
        $table->boolean('active')->default(true);
        $table->timestamps();

        $table->index(['class_id', 'active']);
    });
}

public function down(): void
{
    Schema::dropIfExists('schedules');
}
```

#### Table: `scheduled_lessons`

```php
// database/migrations/2026_03_14_000002_create_scheduled_lessons_table.php

public function up(): void
{
    Schema::create('scheduled_lessons', function (Blueprint $table) {
        $table->id();
        $table->foreignId('schedule_id')
              ->nullable()
              ->constrained('schedules')
              ->nullOnDelete(); // schedule deleted → row survives, schedule_id = null
        $table->foreignId('class_id')
              ->constrained('classes')
              ->cascadeOnDelete();
        $table->dateTime('scheduled_at');
        $table->enum('status', ['scheduled', 'confirmed', 'cancelled'])->default('scheduled');
        $table->text('cancelled_reason')->nullable();
        $table->foreignId('lesson_id')
              ->nullable()
              ->constrained('lessons')
              ->nullOnDelete(); // set by ConfirmScheduledLessonAction
        $table->timestamps();

        $table->index('scheduled_at');
        $table->index('class_id');
        $table->index(['class_id', 'scheduled_at', 'status']);
    });
}

public function down(): void
{
    Schema::dropIfExists('scheduled_lessons');
}
```

**Design decisions:**

- `schedule_id` is nullable: teachers can create one-off `ScheduledLesson` rows without a recurring `Schedule`. These are manually-created single occurrences.
- `lesson_id` is nullable until confirmation. Before confirmation no credit is consumed and no `Lesson` record exists.
- `nullOnDelete` on `schedule_id`: if a `Schedule` is deleted, its generated `ScheduledLesson` rows survive with `schedule_id = NULL`. Historical planned lessons are preserved for audit.
- `nullOnDelete` on `lesson_id`: if a `Lesson` is deleted (via `DeleteLessonAction`), the `ScheduledLesson` row reverts to `lesson_id = NULL` and `status` should be reset to `scheduled`. This transition must be handled in `DeleteLessonAction`.
- Do NOT use `lessons.scheduled_at` as the planning source. `lessons` is a billing/audit record. `scheduled_lessons` is the planning entity. They are separate concerns.
- `duration_minutes` lives on `schedules`, not on individual `scheduled_lessons`. This enables "hours studied" calculation in Feature 3 via a join.

### New Models

#### `Schedule`

```php
// app/Models/Schedule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'weekday',
        'start_time',
        'duration_minutes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'weekday' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    public function scheduledLessons(): HasMany
    {
        return $this->hasMany(ScheduledLesson::class, 'schedule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function weekdayName(): string
    {
        return ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$this->weekday];
    }
}
```

#### `ScheduledLesson`

```php
// app/Models/ScheduledLesson.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScheduledLesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'class_id',
        'scheduled_at',
        'status',
        'cancelled_reason',
        'lesson_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now())
                     ->where('status', 'scheduled');
    }

    public function scopeForStudent(Builder $query, User $student): Builder
    {
        return $query->whereHas('turmaClass', function (Builder $q) use ($student) {
            $q->whereHas('students', fn ($s) => $s->where('users.id', $student->id));
        });
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
```

### New Action Classes

#### `CreateScheduleAction`

```php
// app/Actions/Schedules/CreateScheduleAction.php

namespace App\Actions\Schedules;

use App\Models\Schedule;
use App\Models\TurmaClass;

class CreateScheduleAction
{
    public function execute(TurmaClass $turmaClass, array $data): Schedule
    {
        return Schedule::create([
            'class_id'         => $turmaClass->id,
            'weekday'          => $data['weekday'],         // 0-6
            'start_time'       => $data['start_time'],      // H:i format
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'active'           => true,
        ]);
    }
}
```

#### `GenerateScheduledLessonsAction`

```php
// app/Actions/Schedules/GenerateScheduledLessonsAction.php

namespace App\Actions\Schedules;

use App\Models\Schedule;
use App\Models\ScheduledLesson;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GenerateScheduledLessonsAction
{
    /**
     * Generate ScheduledLesson rows for a schedule, looking ahead $weeksAhead weeks.
     * Skips dates where a row already exists for this schedule + scheduled_at pair.
     */
    public function execute(Schedule $schedule, int $weeksAhead = 4): Collection
    {
        $created = collect();
        $today   = Carbon::today();

        for ($week = 0; $week < $weeksAhead; $week++) {
            $date = $today->copy()
                          ->addWeeks($week)
                          ->next($schedule->weekday === 0 ? Carbon::SUNDAY : $schedule->weekday);

            // Combine date with start_time
            [$hour, $minute] = explode(':', $schedule->start_time);
            $scheduledAt = $date->setTime((int) $hour, (int) $minute, 0);

            // Skip past dates (edge case on week 0)
            if ($scheduledAt->isPast()) {
                continue;
            }

            // Idempotent: skip if already exists
            $exists = ScheduledLesson::where('schedule_id', $schedule->id)
                                     ->where('scheduled_at', $scheduledAt)
                                     ->exists();

            if ($exists) {
                continue;
            }

            $created->push(ScheduledLesson::create([
                'schedule_id'  => $schedule->id,
                'class_id'     => $schedule->class_id,
                'scheduled_at' => $scheduledAt,
                'status'       => 'scheduled',
            ]));
        }

        return $created;
    }

    /**
     * Convenience: run for all active schedules.
     */
    public function executeForAll(int $weeksAhead = 4): void
    {
        Schedule::active()->with('turmaClass')->chunk(50, function ($schedules) use ($weeksAhead) {
            foreach ($schedules as $schedule) {
                $this->execute($schedule, $weeksAhead);
            }
        });
    }
}
```

**Design note:** The action is idempotent. Running it twice for the same schedule window is safe — it checks for existing rows before inserting. This means the artisan command can run daily without generating duplicates.

#### `ConfirmScheduledLessonAction`

```php
// app/Actions/Schedules/ConfirmScheduledLessonAction.php

namespace App\Actions\Schedules;

use App\Actions\Lessons\RegisterLessonAction;
use App\Models\ScheduledLesson;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConfirmScheduledLessonAction
{
    public function __construct(private readonly RegisterLessonAction $registerLesson) {}

    /**
     * Confirm a ScheduledLesson. Calls RegisterLessonAction for each enrolled student.
     * Sets status = 'confirmed' and stores lesson_id (first created lesson, for single-student classes).
     * For group classes, all lessons are created; lesson_id stores the first student's lesson.
     */
    public function execute(ScheduledLesson $scheduledLesson, User $professor, array $data): Collection
    {
        if ($scheduledLesson->status !== 'scheduled') {
            throw new \LogicException(
                "Cannot confirm a scheduled lesson with status '{$scheduledLesson->status}'."
            );
        }

        $turmaClass = $scheduledLesson->turmaClass;
        $students   = $turmaClass->students()->get();

        if ($students->isEmpty()) {
            throw new \RuntimeException(
                "Class #{$turmaClass->id} has no enrolled students — cannot confirm lesson."
            );
        }

        $lessons = collect();

        DB::transaction(function () use ($scheduledLesson, $turmaClass, $students, $professor, $data, &$lessons) {
            foreach ($students as $student) {
                $lesson = $this->registerLesson->execute($turmaClass, $student, $professor, [
                    'title'        => $data['title'] ?? "Aula de {$turmaClass->name}",
                    'notes'        => $data['notes'] ?? null,
                    'conducted_at' => $scheduledLesson->scheduled_at,
                    'status'       => 'completed',
                ]);
                $lessons->push($lesson);
            }

            // Store the first lesson_id as the anchor; all student lessons are linked via lessons table
            $scheduledLesson->update([
                'status'    => 'confirmed',
                'lesson_id' => $lessons->first()->id,
            ]);
        });

        return $lessons;
    }
}
```

#### `CancelScheduledLessonAction`

```php
// app/Actions/Schedules/CancelScheduledLessonAction.php

namespace App\Actions\Schedules;

use App\Models\ScheduledLesson;

class CancelScheduledLessonAction
{
    public function execute(ScheduledLesson $scheduledLesson, ?string $reason = null): ScheduledLesson
    {
        if ($scheduledLesson->status !== 'scheduled') {
            throw new \LogicException(
                "Cannot cancel a scheduled lesson with status '{$scheduledLesson->status}'."
            );
        }

        $scheduledLesson->update([
            'status'           => 'cancelled',
            'cancelled_reason' => $reason,
        ]);

        // Fire event for notification system (Feature 2)
        // event(new ScheduledLessonCancelled($scheduledLesson));

        return $scheduledLesson->fresh();
    }
}
```

### Artisan Command

```php
// app/Console/Commands/GenerateScheduledLessons.php

namespace App\Console\Commands;

use App\Actions\Schedules\GenerateScheduledLessonsAction;
use Illuminate\Console\Command;

class GenerateScheduledLessons extends Command
{
    protected $signature   = 'schedules:generate {--weeks=4 : Number of weeks ahead to generate}';
    protected $description = 'Generate scheduled lesson rows for all active schedules';

    public function handle(GenerateScheduledLessonsAction $action): int
    {
        $weeks = (int) $this->option('weeks');
        $this->info("Generating scheduled lessons for {$weeks} weeks ahead...");

        $action->executeForAll($weeks);

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
```

Register in `bootstrap/app.php` under `$app->commands([])` or in `app/Console/Kernel.php` if it exists. Schedule it in the `schedule()` method:

```php
$schedule->command('schedules:generate')->daily()->at('00:05');
```

### Routes to Add

```php
// In routes/web.php, inside the role:admin,professor middleware group:

// Schedule management
Route::get('/classes/{class}/schedules', [ScheduleController::class, 'index'])->name('classes.schedules.index');
Route::post('/classes/{class}/schedules', [ScheduleController::class, 'store'])->name('classes.schedules.store');
Route::delete('/classes/{class}/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('classes.schedules.destroy');

// Scheduled lesson confirmation / cancellation
Route::post('/scheduled-lessons/{scheduledLesson}/confirm', [ScheduledLessonController::class, 'confirm'])->name('scheduled-lessons.confirm');
Route::post('/scheduled-lessons/{scheduledLesson}/cancel', [ScheduledLessonController::class, 'cancel'])->name('scheduled-lessons.cancel');
```

Add these BEFORE the `/classes/{class}` show route to avoid wildcard conflicts (see CLAUDE.md route ordering note).

### Student Dashboard Integration

In `GetDashboardStatsAction::alunoStats()`, add:

```php
$upcomingScheduledLessons = ScheduledLesson::upcoming()
    ->forStudent($user)
    ->with('turmaClass:id,name', 'schedule:id,duration_minutes')
    ->orderBy('scheduled_at')
    ->take(5)
    ->get()
    ->map(fn ($sl) => [
        'id'               => $sl->id,
        'class_name'       => $sl->turmaClass->name,
        'scheduled_at'     => $sl->scheduled_at->format('d/m/Y H:i'),
        'duration_minutes' => $sl->schedule?->duration_minutes ?? 60,
    ]);
```

Add `'upcomingScheduledLessons' => $upcomingScheduledLessons` to the returned array.

---

## Feature 2 — Notification System

### Problem

No automated notifications exist. Teachers manually contact students by phone or message. There is no system-triggered alert for upcoming lessons, cancellations, or package exhaustion.

### Design Principle

Use Laravel's built-in notification system with `ShouldQueue`. Each notification class implements `toMail()` now and can gain `toWhatsApp()` later without any schema changes. The channel list in `via()` controls delivery — adding WhatsApp is a one-line change per notification class plus a channel driver.

### Schema: Built-in Notifications Table

No custom table. Use Laravel's built-in polymorphic `notifications` table:

```bash
php artisan notifications:table
php artisan migrate
```

This stores: `id` (UUID), `type`, `notifiable_type`, `notifiable_id`, `data` (JSON), `read_at`, `created_at`, `updated_at`.

### Schema: `users.phone` Column (WhatsApp Prep)

```php
// database/migrations/2026_03_14_000003_add_phone_to_users_table.php

public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('phone', 20)->nullable()->after('email');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('phone');
    });
}
```

Add `'phone'` to `User::$fillable`. Add to `HandleInertiaRequests` shared `auth.user` prop if the phone should be editable from the profile page.

### Notification Classes

All notifications live in `app/Notifications/`. All implement `ShouldQueue`.

#### `PackageAlmostFinished`

```php
// app/Notifications/PackageAlmostFinished.php

namespace App\Notifications;

use App\Models\LessonPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PackageAlmostFinished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly LessonPackage $package) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua última aula está chegando — TKL Idiomas')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Você tem **1 aula restante** no seu pacote atual.')
            ->line('Entre em contato com sua professora para renovar antes que o pacote acabe.')
            ->action('Ver meu painel', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'package_almost_finished',
            'package_id' => $this->package->id,
            'remaining'  => $this->package->remaining,
        ];
    }
}
```

#### `PackageFinished`

```php
// app/Notifications/PackageFinished.php

namespace App\Notifications;

use App\Models\LessonPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PackageFinished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly LessonPackage $package) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu pacote de aulas foi esgotado — TKL Idiomas')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Seu pacote de **' . $this->package->total_lessons . ' aulas** foi completamente utilizado.')
            ->line('Para continuar suas aulas, entre em contato com a escola para adquirir um novo pacote.')
            ->action('Ver meu painel', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'package_finished',
            'package_id' => $this->package->id,
        ];
    }
}
```

#### `UpcomingLessonReminder`

Depends on Feature 1 (scheduling). Fires 24h before a `ScheduledLesson`.

```php
// app/Notifications/UpcomingLessonReminder.php

namespace App\Notifications;

use App\Models\ScheduledLesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpcomingLessonReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ScheduledLesson $scheduledLesson) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
        // Future: add 'whatsapp' here without changing notification logic
    }

    public function toMail(object $notifiable): MailMessage
    {
        $className = $this->scheduledLesson->turmaClass->name;
        $dateTime  = $this->scheduledLesson->scheduled_at->format('d/m/Y \à\s H:i');

        return (new MailMessage)
            ->subject("Lembrete: Aula de {$className} amanhã — TKL Idiomas")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Você tem uma aula de **{$className}** amanhã, {$dateTime}.")
            ->line('Não se esqueça de preparar o material da aula.')
            ->action('Ver meu painel', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                  => 'upcoming_lesson_reminder',
            'scheduled_lesson_id'   => $this->scheduledLesson->id,
            'class_name'            => $this->scheduledLesson->turmaClass->name,
            'scheduled_at'          => $this->scheduledLesson->scheduled_at->toIso8601String(),
        ];
    }
}
```

#### `LessonCancelled`

```php
// app/Notifications/LessonCancelled.php

namespace App\Notifications;

use App\Models\ScheduledLesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ScheduledLesson $scheduledLesson) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $className = $this->scheduledLesson->turmaClass->name;
        $dateTime  = $this->scheduledLesson->scheduled_at->format('d/m/Y \à\s H:i');
        $reason    = $this->scheduledLesson->cancelled_reason;

        $mail = (new MailMessage)
            ->subject("Aula cancelada: {$className} em {$dateTime} — TKL Idiomas")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Sua aula de **{$className}** prevista para {$dateTime} foi cancelada.");

        if ($reason) {
            $mail->line("Motivo: {$reason}");
        }

        return $mail->line('Entre em contato com a escola para reagendamento.')
                    ->action('Ver meu painel', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                => 'lesson_cancelled',
            'scheduled_lesson_id' => $this->scheduledLesson->id,
            'cancelled_reason'    => $this->scheduledLesson->cancelled_reason,
        ];
    }
}
```

#### `NewMaterialUploaded`

```php
// app/Notifications/NewMaterialUploaded.php

namespace App\Notifications;

use App\Models\Material;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMaterialUploaded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Material $material) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Novo material disponível: {$this->material->title} — TKL Idiomas")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Um novo material foi adicionado à sua turma: **{$this->material->title}**.")
            ->action('Baixar material', route('materials.download', $this->material));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'new_material_uploaded',
            'material_id' => $this->material->id,
            'title'       => $this->material->title,
            'class_id'    => $this->material->class_id,
        ];
    }
}
```

#### `StudentWithoutPackage`

Notifies the professor when a student has no active package. Fired in `RegisterLessonAction` only when the action fails due to no active package (catch the `ModelNotFoundException`).

```php
// app/Notifications/StudentWithoutPackage.php

namespace App\Notifications;

use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentWithoutPackage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $student,
        private readonly TurmaClass $turmaClass
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Aluno sem pacote ativo: {$this->student->name} — TKL Idiomas")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O aluno **{$this->student->name}** não tem pacote de aulas ativo para a turma **{$this->turmaClass->name}**.")
            ->line('A aula não pôde ser registrada. Verifique e renove o pacote antes de tentar novamente.')
            ->action('Gerenciar pacotes', url("/admin/users/{$this->student->id}/packages"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'student_without_package',
            'student_id' => $this->student->id,
            'class_id'   => $this->turmaClass->id,
        ];
    }
}
```

#### `StudentAbsent`

```php
// app/Notifications/StudentAbsent.php

namespace App\Notifications;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentAbsent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $student,
        private readonly Lesson $lesson
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'student_absent',
            'student_id' => $this->student->id,
            'lesson_id'  => $this->lesson->id,
            'date'       => $this->lesson->conducted_at?->format('d/m/Y'),
        ];
    }
}
```

### Integration Points in Existing Actions

#### In `RegisterLessonAction::execute()`

After `$package->increment('used_lessons')` and `Lesson::create(...)`:

```php
// Reload to get updated used_lessons
$package->refresh();

if ($package->isExhausted()) {
    $student->notify(new \App\Notifications\PackageFinished($package));
} elseif ($package->remaining === 1) {
    $student->notify(new \App\Notifications\PackageAlmostFinished($package));
}
```

#### In `CancelScheduledLessonAction::execute()`

After updating status to 'cancelled', notify enrolled students:

```php
$students = $scheduledLesson->turmaClass->students;
foreach ($students as $student) {
    $student->notify(new \App\Notifications\LessonCancelled($scheduledLesson));
}
```

#### In `UploadMaterialAction::execute()`

After `Material::create(...)`:

```php
$students = $material->turmaClass->students;
foreach ($students as $student) {
    $student->notify(new \App\Notifications\NewMaterialUploaded($material));
}
```

### Artisan Command: `SendLessonReminders`

```php
// app/Console/Commands/SendLessonReminders.php

namespace App\Console\Commands;

use App\Models\ScheduledLesson;
use App\Notifications\UpcomingLessonReminder;
use Illuminate\Console\Command;

class SendLessonReminders extends Command
{
    protected $signature   = 'notifications:send-reminders';
    protected $description = 'Send 24-hour lesson reminders to students with upcoming scheduled lessons';

    public function handle(): int
    {
        $tomorrow = now()->addDay();

        $scheduledLessons = ScheduledLesson::where('status', 'scheduled')
            ->whereBetween('scheduled_at', [
                $tomorrow->copy()->startOfDay(),
                $tomorrow->copy()->endOfDay(),
            ])
            ->with('turmaClass.students')
            ->get();

        $this->info("Found {$scheduledLessons->count()} lessons to remind.");

        foreach ($scheduledLessons as $scheduledLesson) {
            foreach ($scheduledLesson->turmaClass->students as $student) {
                $student->notify(new UpcomingLessonReminder($scheduledLesson));
            }
        }

        $this->info('Reminders dispatched to queue.');
        return Command::SUCCESS;
    }
}
```

Schedule this command:

```php
$schedule->command('notifications:send-reminders')->dailyAt('08:00');
```

### WhatsApp Channel — Future Addition

When the time comes, create a channel driver:

```php
// app/Channels/WhatsAppChannel.php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->phone;
        if (! $phone || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);
        // Call WhatsApp Business API or Twilio here
    }
}
```

Adding WhatsApp delivery to any notification then requires:
1. Adding `'whatsapp'` to the `via()` array.
2. Implementing `toWhatsApp()` on the notification class.
3. No schema changes — `users.phone` already exists.

---

## Feature 3 — Student Progress System

### Problem

Students have no visibility into their learning journey. There are no motivation metrics, no streaks, no milestone tracking. The dashboard shows a package status but not a progress narrative.

### Design Decision: Dynamic Calculation, No Stored Progress

The `lessons` table already has `conducted_at`, `status`, and joins to `TurmaClass`. Counting completed lessons is a single query. The `scheduled_lessons` table (Feature 1) provides `duration_minutes` via `schedules` for "hours studied." At current scale (single school, under 1000 students), dynamic calculation is fast and always accurate.

**Do NOT add a `student_progress` table now.** Add a `student_progress` cache table only if query time exceeds 200ms for students with 500+ lessons, at which point add `calculated_at` and invalidate on lesson insert/delete.

### New Action: `GetProgressStatsAction`

```php
// app/Actions/GetProgressStatsAction.php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\ScheduledLesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GetProgressStatsAction
{
    public function execute(User $student): array
    {
        $lessonsCompleted = Lesson::where('student_id', $student->id)
            ->completed()
            ->count();

        $hoursStudied = $this->calculateHoursStudied($student->id);
        $currentStreak = $this->calculateStreak($student->id);
        $nextMilestone = $this->nextMilestone($lessonsCompleted);

        return [
            'lessonsCompleted'  => $lessonsCompleted,
            'hoursStudied'      => $hoursStudied,
            'currentStreak'     => $currentStreak,
            'nextMilestone'     => $nextMilestone,
            'milestoneProgress' => $nextMilestone > 0
                ? round(($lessonsCompleted / $nextMilestone) * 100, 1)
                : 100.0,
        ];
    }

    private function calculateHoursStudied(int $studentId): float
    {
        // Primary: sum duration_minutes from schedules via scheduled_lessons join
        $minutesViaSchedule = DB::table('lessons')
            ->join('scheduled_lessons', 'scheduled_lessons.lesson_id', '=', 'lessons.id')
            ->join('schedules', 'schedules.id', '=', 'scheduled_lessons.schedule_id')
            ->where('lessons.student_id', $studentId)
            ->where('lessons.status', 'completed')
            ->sum('schedules.duration_minutes');

        if ($minutesViaSchedule > 0) {
            return round($minutesViaSchedule / 60, 1);
        }

        // Fallback: assume 60 minutes per lesson when no schedule link exists
        $count = Lesson::where('student_id', $studentId)->completed()->count();
        return (float) $count;
    }

    private function calculateStreak(int $studentId): int
    {
        // Fetch all weeks (ISO year-week) that had at least 1 completed lesson
        $weeks = DB::table('lessons')
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->whereNotNull('conducted_at')
            ->orderByDesc('conducted_at')
            ->get(['conducted_at'])
            ->map(fn ($row) => \Carbon\Carbon::parse($row->conducted_at)->format('o-W'))
            ->unique()
            ->values();

        if ($weeks->isEmpty()) {
            return 0;
        }

        // Build the expected consecutive week series starting from current week
        $streak = 0;
        $expectedWeek = \Carbon\Carbon::now()->format('o-W');

        foreach ($weeks as $week) {
            if ($week === $expectedWeek) {
                $streak++;
                $expectedWeek = \Carbon\Carbon::now()
                    ->subWeeks($streak)
                    ->format('o-W');
            } else {
                break;
            }
        }

        return $streak;
    }

    private function nextMilestone(int $completed): int
    {
        $milestones = [5, 10, 20, 30, 50, 75, 100, 150, 200];
        return collect($milestones)->first(fn ($m) => $m > $completed) ?? ($completed + 50);
    }
}
```

### Integration with `GetDashboardStatsAction`

In `GetDashboardStatsAction::alunoStats()`, inject and call `GetProgressStatsAction`:

```php
// Constructor injection in GetDashboardStatsAction:
public function __construct(private readonly GetProgressStatsAction $progressStats) {}

// Inside alunoStats():
'progress' => $this->progressStats->execute($user),
```

The returned array shape for Inertia:

```php
'progress' => [
    'lessonsCompleted'  => 32,
    'hoursStudied'      => 32.0,
    'currentStreak'     => 3,      // consecutive weeks with >= 1 completed lesson
    'nextMilestone'     => 40,
    'milestoneProgress' => 80.0,   // percentage toward next milestone
]
```

### Frontend Progress Card

The progress section is a new card on the student dashboard (`resources/js/Pages/Dashboard.jsx`). It sits below the active package card. Key render logic:

- **Progress bar:** `(lessonsCompleted / nextMilestone) * 100`%. Fill color: indigo-600.
- **Streak badge:** Only render when `currentStreak >= 2`. Text: "X semanas consecutivas".
- **Hours studied:** Format as `"32h estudadas"`. If `< 1`, show in minutes.
- **Milestone chip:** "Próxima conquista: 40 aulas" with the progress percentage.

No new Inertia page required — extend the existing `Dashboard.jsx` with a new `<ProgressCard>` component in `resources/js/Components/ProgressCard.jsx`.

---

## Feature 5 — SaaS Multi-Tenancy Preparation

### Problem

Every database table is globally scoped. Adding a second school to the platform would mix all student, lesson, and payment data. The architecture review identified this as a medium-priority future concern. Phase 1 is zero-risk and should be done now.

### Phase 1 — Do Now: `schools` Table + Nullable `school_id` on Users

Zero behavior changes. Zero data migration. All existing users get `school_id = NULL`, meaning standalone mode.

```php
// database/migrations/2026_03_14_000004_create_schools_table.php

public function up(): void
{
    Schema::create('schools', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique(); // subdomain or URL identifier: 'tkl', 'escola-xyz'
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
// database/migrations/2026_03_14_000005_add_school_id_to_users_table.php

public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->foreignId('school_id')
              ->nullable()
              ->after('id')
              ->constrained('schools')
              ->nullOnDelete(); // school deleted → users survive, school_id = null
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

**New `School` model:**

```php
// app/Models/School.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = ['name', 'slug', 'email', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
```

Add `school_id` to `User::$fillable` and add the relationship:

```php
// In app/Models/User.php — add to $fillable:
'school_id',

// Add relationship:
public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(School::class);
}
```

### Phase 2 — When First Second School Onboards

Add `school_id` to: `classes`, `lesson_packages`, `lessons`, `materials`, `schedules`, `scheduled_lessons`, `payments`.

Implement `HasSchool` trait with a global scope:

```php
// app/Concerns/HasSchool.php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

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
            // school_id = null → no scope applied (standalone mode)
        });
    }
}
```

Apply to each model: `use HasSchool;` after confirming `school_id` column exists on its table.

Add a `ResolveTenantMiddleware` that reads the subdomain from the request, finds the matching `School` by `slug`, and stores the resolved `school_id` on the authenticated user's session — so `auth()->user()->school_id` is always set for scoped requests.

**Migration safety rules:**
- Never add `school_id NOT NULL` without a default. Always use `nullable()` first, backfill, then add the constraint.
- Use `nullOnDelete()` (not `cascadeOnDelete()`) on `school_id` foreign keys. Data must survive school record updates.

### Phase 3 — SaaS Billing

```php
// Add to schools table when needed:
$table->enum('plan', ['free', 'basic', 'pro'])->default('free');

// Separate subscriptions table (custom or via Laravel Cashier):
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->string('plan');
    $table->timestamp('starts_at');
    $table->timestamp('ends_at')->nullable();
    $table->timestamps();
});
```

Gate features using `$school->plan` in middleware or a `PlanGate` service. Do not mix plan logic into models or action classes.

**Architecture recommendation:** Use the `HasSchool` trait approach rather than Spatie Multitenancy or Tenancy for Laravel. The stack is simple enough that a full multitenancy package adds unnecessary complexity. The trait + middleware approach is transparent, debuggable, and requires no third-party updates.

---

## Cross-Cutting Notes

### Existing Code That Must NOT Be Bypassed

- `RegisterLessonAction` uses `DB::transaction()` + `lockForUpdate()` on the `LessonPackage`. Any new action that confirms a lesson (including `ConfirmScheduledLessonAction`) MUST go through `RegisterLessonAction` — never call `$package->increment('used_lessons')` directly.
- `used_lessons` and `role` are intentionally excluded from `$fillable` on their respective models. Do not add them. Modify `used_lessons` only through `RegisterLessonAction` (increment) and `DeleteLessonAction` (decrement).
- `LessonPackage::scopeActive()` encapsulates the two-condition active check. Any query needing active packages must use this scope, not raw `where` clauses.

### Queue Configuration

All `ShouldQueue` notifications require a running queue worker. During development:

```bash
php artisan queue:listen
```

The existing `composer run dev` command (defined in `composer.json`) should have queue listening added alongside server + Vite. In production, use a supervisor-managed `php artisan queue:work`.

### Testing Notes

- `RefreshDatabase` is already used in `tests/Unit/Models/`. New tests for actions follow the same pattern.
- `ScheduledLesson` factory needs `schedule_id` (nullable), `class_id`, `scheduled_at`, and `status`. Create it alongside the model.
- `Schedule` factory: `weekday` (0-6), `start_time` ('10:00'), `duration_minutes` (60), `active` (true).
- `GenerateScheduledLessonsAction` tests should assert idempotency: running `execute()` twice produces the same number of rows as running it once.
- For notification tests, use `Notification::fake()` and assert `Notification::assertSentTo($student, PackageFinished::class)` after calling `RegisterLessonAction` with a package at `used_lessons = total_lessons - 1`.
