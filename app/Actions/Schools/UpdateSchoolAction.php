<?php
namespace App\Actions\Schools;
use App\Models\School;
/**
 * Updates an existing school's details.
 *
 * Slug changes affect the school's URL identifier across the platform.
 * Deactivating a school (active = false) does not cascade to users or data.
 */
class UpdateSchoolAction
{
    /**
     * @param School $school The school to update
     * @param array  $data   Validated data: name, slug, email (optional), active (optional)
     * @return School        The updated school instance
     */
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
