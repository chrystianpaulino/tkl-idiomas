<?php
namespace App\Actions\Schools;
use App\Models\School;
class CreateSchoolAction
{
    public function execute(array $data): School
    {
        return School::create([
            'name'   => $data['name'],
            'slug'   => $data['slug'],
            'email'  => $data['email'] ?? null,
            'active' => $data['active'] ?? true,
        ]);
    }
}
