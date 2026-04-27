<?php

namespace App\Actions\Materials;

use App\Models\Material;
use App\Models\TurmaClass;
use App\Models\User;
use App\Notifications\NewMaterialUploaded;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads a teaching material file to public storage and notifies enrolled students.
 *
 * Files are stored under 'materials/' on the public disk. After creation, all
 * students enrolled in the class receive a NewMaterialUploaded notification.
 *
 * @see DeleteMaterialAction For the reverse operation (file cleanup + record deletion)
 */
class UploadMaterialAction
{
    /**
     * @param  TurmaClass  $turmaClass  The class this material is for
     * @param  User  $uploader  The admin or professor uploading the file
     * @param  UploadedFile  $file  The uploaded file (validated by StoreMaterialRequest)
     * @param  array  $data  Validated data: title (required), description (optional)
     * @return Material The persisted material with file_path set
     */
    public function execute(TurmaClass $turmaClass, User $uploader, UploadedFile $file, array $data): Material
    {
        $path = Storage::disk('public')->put('materials', $file);

        // class_id, uploaded_by and school_id are intentionally outside
        // Material::$fillable: they fix tenant/ownership for the file. This
        // action is the only writer that may set them.
        $material = new Material;
        $material->class_id = $turmaClass->id;
        $material->uploaded_by = $uploader->id;
        $material->title = $data['title'];
        $material->file_path = $path;
        $material->description = $data['description'] ?? null;
        $material->save();

        $material->load('turmaClass.students');
        foreach ($material->turmaClass->students as $student) {
            $student->notify(new NewMaterialUploaded($material));
        }

        return $material;
    }
}
