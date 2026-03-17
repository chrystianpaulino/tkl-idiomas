<?php

namespace App\Actions\Materials;

use App\Models\Material;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadMaterialAction
{
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
