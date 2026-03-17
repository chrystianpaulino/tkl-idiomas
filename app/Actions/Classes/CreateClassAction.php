<?php

namespace App\Actions\Classes;

use App\Models\TurmaClass;
use App\Models\User;

class CreateClassAction
{
    public function execute(array $data, User $professor): TurmaClass
    {
        return TurmaClass::create([
            'name' => $data['name'],
            'professor_id' => $professor->id,
            'description' => $data['description'] ?? null,
        ]);
    }
}
