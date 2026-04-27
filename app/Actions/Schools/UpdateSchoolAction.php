<?php

namespace App\Actions\Schools;

use App\Models\School;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Updates an existing school's details, including white-label visual identity.
 *
 * Slug changes affect the school's URL identifier across the platform.
 * Deactivating a school (active = false) does not cascade to users or data.
 *
 * Logo handling:
 *   - When a new UploadedFile is provided, the previous logo file is deleted
 *     from the public disk before the replacement is persisted.
 *   - When `remove_logo === true`, the existing file is deleted and the
 *     column is set to NULL (the UI will fall back to the textual school name).
 *
 * Color updates accept hex values validated upstream by UpdateSchoolRequest;
 * passing null/empty leaves the existing value untouched.
 */
class UpdateSchoolAction
{
    /**
     * @param  School  $school  The school to update
     * @param  array  $data  Validated data (name, slug, email?, active?, logo?, remove_logo?, primary_color?, secondary_color?)
     * @return School The updated school instance
     */
    public function execute(School $school, array $data): School
    {
        $attributes = Arr::only($data, [
            'name',
            'slug',
            'email',
            'active',
            'primary_color',
            'secondary_color',
        ]);

        // Strip empty color strings so the existing value is preserved instead of
        // being overwritten with an invalid blank (defaults live at the DB layer).
        foreach (['primary_color', 'secondary_color'] as $colorKey) {
            if (array_key_exists($colorKey, $attributes) && empty($attributes[$colorKey])) {
                unset($attributes[$colorKey]);
            }
        }

        // ── Logo replacement ──────────────────────────────────────
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            if ($school->logo_url) {
                Storage::disk('public')->delete($school->logo_url);
            }
            $attributes['logo_url'] = Storage::disk('public')->put('schools/logos', $data['logo']);
        } elseif (! empty($data['remove_logo'])) {
            if ($school->logo_url) {
                Storage::disk('public')->delete($school->logo_url);
            }
            $attributes['logo_url'] = null;
        }

        $school->update($attributes);

        // Capture only the keys that actually changed in this request, omitting
        // the file content itself (we just record whether logo was touched).
        $loggedChanges = array_keys($school->getChanges());
        Audit::log('school.updated', [
            'school_id' => $school->id,
            'slug' => $school->slug,
            'changed_fields' => $loggedChanges,
        ]);

        return $school;
    }
}
