<?php

namespace App\Http\Controllers;

use App\Actions\Schedules\CreateScheduleAction;
use App\Actions\Schedules\UpdateScheduleAction;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Models\TurmaClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD controller for recurring Schedule rules.
 *
 * Listing is role-scoped: school admins see every schedule in their school
 * (BelongsToSchool global scope), professors see only schedules of their
 * classes, students see schedules of classes they are enrolled in.
 *
 * Create/update/delete are gated by SchedulePolicy. Cross-tenant validation
 * happens at the FormRequest layer (class_id must belong to the actor's school).
 *
 * @see CreateScheduleAction For schedule creation
 * @see UpdateScheduleAction For schedule updates
 * @see SchedulePolicy       For authorization rules
 */
class ScheduleController extends Controller
{
    /**
     * List schedules visible to the authenticated user, filtered by role.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Schedule::with(['turmaClass.professor', 'turmaClass.school']);

        if ($user->isProfessor()) {
            $query->whereHas('turmaClass', fn ($q) => $q->where('professor_id', $user->id));
        } elseif ($user->isAluno()) {
            $query->whereHas('turmaClass.students', fn ($q) => $q->where('users.id', $user->id));
        }

        $schedules = $query->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'class' => $schedule->turmaClass ? [
                    'id' => $schedule->turmaClass->id,
                    'name' => $schedule->turmaClass->name,
                    'professor' => $schedule->turmaClass->professor?->only('id', 'name'),
                ] : null,
                'weekday' => $schedule->weekday,
                'weekday_name' => $schedule->weekdayName(),
                'start_time' => substr($schedule->start_time, 0, 5),
                'duration_minutes' => $schedule->duration_minutes,
                'active' => $schedule->active,
                'can' => [
                    'update' => $user->can('update', $schedule),
                    'delete' => $user->can('delete', $schedule),
                ],
            ]);

        return Inertia::render('Schedules/Index', [
            'schedules' => $schedules,
            'can' => [
                'create' => $user->can('create', Schedule::class),
            ],
        ]);
    }

    /**
     * Form for creating a new schedule rule. Lists classes the user may
     * schedule against (own classes for professors; school-wide for admins).
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Schedule::class);

        $classes = $this->classesAvailableTo($request->user())
            ->map(fn (TurmaClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'professor' => $c->professor?->only('id', 'name'),
            ]);

        return Inertia::render('Schedules/Create', [
            'classes' => $classes,
        ]);
    }

    public function store(StoreScheduleRequest $request, CreateScheduleAction $action): RedirectResponse
    {
        $turmaClass = TurmaClass::findOrFail($request->validated('class_id'));

        // Defense-in-depth: form request already filters classes by school,
        // but the User model is not BelongsToSchool scoped — re-check here.
        if (! $request->user()->isSuperAdmin() && $turmaClass->school_id !== $request->user()->school_id) {
            abort(403);
        }

        $action->execute($turmaClass, $request->validated());

        return redirect()
            ->route('schedules.index')
            ->with('success', 'Agendamento recorrente criado com sucesso.');
    }

    public function edit(Request $request, Schedule $schedule): Response
    {
        $this->authorize('update', $schedule);

        $classes = $this->classesAvailableTo($request->user())
            ->map(fn (TurmaClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'professor' => $c->professor?->only('id', 'name'),
            ]);

        return Inertia::render('Schedules/Edit', [
            'schedule' => [
                'id' => $schedule->id,
                'class_id' => $schedule->class_id,
                'weekday' => $schedule->weekday,
                'start_time' => substr($schedule->start_time, 0, 5),
                'duration_minutes' => $schedule->duration_minutes,
                'active' => (bool) $schedule->active,
            ],
            'classes' => $classes,
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule, UpdateScheduleAction $action): RedirectResponse
    {
        $this->authorize('update', $schedule);

        $action->execute($schedule, $request->validated());

        return redirect()
            ->route('schedules.index')
            ->with('success', 'Agendamento atualizado com sucesso.');
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $this->authorize('delete', $schedule);

        $schedule->delete();

        return redirect()
            ->route('schedules.index')
            ->with('success', 'Agendamento removido com sucesso.');
    }

    /**
     * Build the candidate class list for select fields, scoped to the user's
     * role and school. Professors see only classes they teach.
     */
    private function classesAvailableTo($user)
    {
        return TurmaClass::with('professor')
            ->when($user->isProfessor(), fn ($q) => $q->where('professor_id', $user->id))
            ->orderBy('name')
            ->get();
    }
}
