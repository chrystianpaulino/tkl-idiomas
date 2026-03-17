<?php

namespace App\Actions\Classes;

use App\Models\TurmaClass;
use App\Models\User;

/**
 * Creates a new class (turma) and assigns a professor to it.
 *
 * Called by ClassController::store after StoreClassRequest validation.
 * Students are enrolled separately via EnrollStudentAction.
 */
class CreateClassAction
{
    /**
     * @param array $data      Validated data: name (required), description (optional)
     * @param User  $professor The professor to assign as the class teacher
     * @return TurmaClass      The newly created class
     */
    public function execute(array $data, User $professor): TurmaClass
    {
        return TurmaClass::create([
            'name' => $data['name'],
            'professor_id' => $professor->id,
            'description' => $data['description'] ?? null,
        ]);
    }
}
