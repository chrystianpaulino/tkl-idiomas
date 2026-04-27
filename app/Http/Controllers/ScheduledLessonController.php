<?php

namespace App\Http\Controllers;

use App\Actions\Schedules\CancelScheduledLessonAction;
use App\Actions\Schedules\ConfirmScheduledLessonAction;
use App\Http\Requests\CancelScheduledLessonRequest;
use App\Http\Requests\ConfirmScheduledLessonRequest;
use App\Models\ScheduledLesson;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the calendar slots materialized from recurring Schedule rules.
 *
 * Listing is role-filtered: admins see every slot in their school (via the
 * BelongsToSchool global scope), professors see slots of classes they teach,
 * students see slots of classes where they are enrolled.
 *
 * Confirmation creates Lesson records and consumes package credits via
 * ConfirmScheduledLessonAction. Cancellation only marks the slot as cancelled.
 *
 * @see ConfirmScheduledLessonAction For confirmation logic (calls RegisterLessonAction)
 * @see CancelScheduledLessonAction  For cancellation logic
 * @see ScheduledLessonPolicy        For authorization rules
 */
class ScheduledLessonController extends Controller
{
    /**
     * List scheduled lessons. Filters via query string:
     *   - period: 'upcoming' (default), 'past', 'all'
     *   - status: 'scheduled', 'confirmed', 'cancelled', 'all' (default)
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $request->string('period', 'upcoming')->toString();
        $status = $request->string('status', 'all')->toString();

        $query = ScheduledLesson::with(['turmaClass.professor', 'schedule']);

        if ($user->isProfessor()) {
            $query->whereHas('turmaClass', fn ($q) => $q->where('professor_id', $user->id));
        } elseif ($user->isAluno()) {
            $query->whereHas('turmaClass.students', fn ($q) => $q->where('users.id', $user->id));
        }

        if ($period === 'upcoming') {
            $query->where('scheduled_at', '>=', now()->startOfDay());
        } elseif ($period === 'past') {
            $query->where('scheduled_at', '<', now()->startOfDay());
        }

        if (in_array($status, ['scheduled', 'confirmed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $items = $query->orderBy('scheduled_at')
            ->limit(200)
            ->get()
            ->map(fn (ScheduledLesson $sl) => [
                'id' => $sl->id,
                'scheduled_at' => $sl->scheduled_at?->toIso8601String(),
                'status' => $sl->status,
                'cancelled_reason' => $sl->cancelled_reason,
                'duration_minutes' => $sl->schedule?->duration_minutes,
                'class' => $sl->turmaClass ? [
                    'id' => $sl->turmaClass->id,
                    'name' => $sl->turmaClass->name,
                    'professor' => $sl->turmaClass->professor?->only('id', 'name'),
                ] : null,
                'can' => [
                    'confirm' => $user->can('confirm', $sl),
                    'cancel' => $user->can('cancel', $sl),
                ],
            ]);

        return Inertia::render('ScheduledLessons/Index', [
            'scheduledLessons' => $items,
            'filters' => [
                'period' => $period,
                'status' => $status,
            ],
        ]);
    }

    public function confirm(
        ConfirmScheduledLessonRequest $request,
        ScheduledLesson $scheduledLesson,
        ConfirmScheduledLessonAction $action,
    ): RedirectResponse {
        try {
            $action->execute($scheduledLesson, $request->user(), $request->validated());
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (ModelNotFoundException) {
            return back()->with('error', 'Algum aluno desta turma não possui pacote ativo. Registre/renove o pacote antes de confirmar.');
        }

        return back()->with('success', 'Aula confirmada e registrada para os alunos da turma.');
    }

    public function cancel(
        CancelScheduledLessonRequest $request,
        ScheduledLesson $scheduledLesson,
        CancelScheduledLessonAction $action,
    ): RedirectResponse {
        try {
            $action->execute($scheduledLesson, $request->validated()['reason'] ?? null);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Agendamento cancelado.');
    }
}
