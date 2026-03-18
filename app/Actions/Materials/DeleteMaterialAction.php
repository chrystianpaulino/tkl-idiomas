<?php

namespace App\Actions\Materials;

use App\Models\Material;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a material record and its associated file from public storage.
 *
 * Cleans up the physical file first, then deletes the database record.
 * If the file is already missing from disk, Storage::delete silently succeeds.
 */
class DeleteMaterialAction
{
    /**
     * @param  Material  $material  The material to delete (file will be removed from disk)
     */
    public function execute(Material $material): void
    {
        Storage::disk('public')->delete($material->file_path);
        $material->delete();
    }
}
