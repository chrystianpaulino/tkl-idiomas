<?php

namespace App\Actions\Materials;

use App\Models\Material;
use Illuminate\Support\Facades\Storage;

class DeleteMaterialAction
{
    public function execute(Material $material): void
    {
        Storage::disk('public')->delete($material->file_path);
        $material->delete();
    }
}
