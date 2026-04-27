<?php

namespace App\Http\Requests;

use App\Models\TurmaClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates updates to an existing schedule rule.
 *
 * Same field rules as StoreScheduleRequest but every field is optional
 * (PATCH-style partial update). Authorization is delegated to
 * SchedulePolicy::update via $this->authorize() in the controller.
 */
class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('schedule')) ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'class_id' => [
                'sometimes',
                'required',
                Rule::exists('classes', 'id')
                    ->when(
                        ! $user->isSuperAdmin(),
                        fn ($rule) => $rule->where('school_id', $user->school_id),
                    ),
            ],
            'weekday' => ['sometimes', 'required', 'integer', 'between:0,6'],
            'start_time' => ['sometimes', 'required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:15', 'max:240'],
            'active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

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
                $v->errors()->add('class_id', 'Você só pode mover o agendamento para turmas que leciona.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'class_id.exists' => 'A turma selecionada é inválida ou não pertence à sua escola.',
            'start_time.regex' => 'O horário deve estar no formato HH:MM (ex: 19:00).',
            'weekday.between' => 'O dia da semana deve ser entre 0 (domingo) e 6 (sábado).',
        ];
    }
}
