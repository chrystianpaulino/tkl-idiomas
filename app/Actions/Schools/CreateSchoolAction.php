<?php

namespace App\Actions\Schools;

use App\Models\School;

/**
 * Creates a new school (tenant) on the platform.
 *
 * Schools are the multi-tenancy root. Once created, users can be assigned
 * to the school via their school_id. New schools default to active.
 */
class CreateSchoolAction
{
    /**
     * @param  array  $data  Validated data: name, slug (unique), email (optional), active (optional, default true)
     * @return School The newly created school
     */
    public function execute(array $data): School
    {
        return School::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'email' => $data['email'] ?? null,
            'active' => $data['active'] ?? true,
        ]);
    }
}
