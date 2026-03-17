<?php

namespace App\Actions\Materials;

use App\Models\Material;
use App\Models\TurmaClass;
use App\Models\User;
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
     * @param TurmaClass   $turmaClass The class this material is for
     * @param User         $uploader   The admin or professor uploading the file
     * @param UploadedFile $file       The uploaded file (validated by StoreMaterialRequest)
     * @param array        $data       Validated data: title (required), description (optional)
     * @return Material                The persisted material with file_path set
     */
    public function execute(TurmaClass $turmaClass, User $uploader, UploadedFile $file, array $data): Material
    {
        $path = Storage::disk('public')->put('materials', $file);

        $material = Material::create([
            'class_id' => $turmaClass->id,
            'uploaded_by' => $uploader->id,
            'title' => $data['title'],
            'file_path' => $path,
            'description' => $data['description'] ?? null,
        ]);

        $material->load('turmaClass.students');
        foreach ($material->turmaClass->students as $student) {
            $student->notify(new \App\Notifications\NewMaterialUploaded($material));
        }

        return $material;
    }
}
