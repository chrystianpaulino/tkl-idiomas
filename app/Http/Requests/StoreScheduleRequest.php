<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use App\Models\TurmaClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates and authorizes requests to create a recurring schedule rule.
 *
 * Authorization is gated by SchedulePolicy::create (school_admin or professor).
 * The class_id must reference an existing class in the same school as the
 * authenticated user (super_admin may reference any school).
 *
 * Additional rule: when the actor is a professor, the target class must be
 * one they teach. School admins may create schedules for any class within
 * their school.
 */
class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Schedule::class) ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'class_id' => [
                'required',
                Rule::exists('classes', 'id')
                    ->when(
                        ! $user->isSuperAdmin(),
                        fn ($rule) => $rule->where('school_id', $user->school_id),
                    ),
            ],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Defense-in-depth: a professor may only create schedules for classes
     * where they are the assigned teacher. Validated after primary rules so
     * the class actually exists.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $user = $this->user();
            if ($user === null || ! $user->isProfessor()) {
                return;
            }

            $classId = $this->input('class_id');
            if ($classId === null) {
                return;
            }

            $turmaClass = TurmaClass::query()->find($classId);
            if ($turmaClass !== null && $turmaClass->professor_id !== $user->id) {
                $v->errors()->add('class_id', 'Você só pode agendar aulas para turmas que leciona.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'class_id.exists' => 'A turma selecionada é inválida ou não pertence à sua escola.',
            'start_time.regex' => 'O horário deve estar no formato HH:MM (ex: 19:00).',
            'weekday.between' => 'O dia da semana deve ser entre 0 (domingo) e 6 (sábado).',
            'duration_minutes.min' => 'A duração mínima é 15 minutos.',
            'duration_minutes.max' => 'A duração máxima é 240 minutos.',
        ];
    }
}
