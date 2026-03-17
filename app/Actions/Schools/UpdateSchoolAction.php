<?php
namespace App\Actions\Schools;
use App\Models\School;
class UpdateSchoolAction
{
    public function execute(School $school, array $data): School
    {
        $school->update([
            'name'   => $data['name'],
            'slug'   => $data['slug'],
            'email'  => $data['email'] ?? null,
            'active' => $data['active'] ?? true,
        ]);
        return $school;
    }
}
