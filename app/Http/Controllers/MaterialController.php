<?php

namespace App\Http\Controllers;

use App\Actions\Materials\DeleteMaterialAction;
use App\Actions\Materials\UploadMaterialAction;
use App\Http\Requests\StoreMaterialRequest;
use App\Models\Material;
use App\Models\TurmaClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages teaching materials (upload, list, download, delete) for a class.
 *
 * Upload and delete are restricted to admins and the class professor.
 * Download is available to anyone who can view the class (including students).
 * File storage and notifications are handled by UploadMaterialAction/DeleteMaterialAction.
 *
 * @see MaterialPolicy For authorization rules (create, delete, download)
 */
class MaterialController extends Controller
{
    public function index(TurmaClass $class): Response
    {
        $this->authorize('view', $class);

        return Inertia::render('Materials/Index', [
            'turmaClass' => $class->only('id', 'name'),
            'materials' => $class->materials()->with('uploader')->latest()->get(),
        ]);
    }

    public function create(Request $request, TurmaClass $class): Response
    {
        $this->authorize('create', [Material::class, $class]);

        return Inertia::render('Materials/Create', [
            'turmaClass' => $class->only('id', 'name'),
        ]);
    }

    public function store(StoreMaterialRequest $request, TurmaClass $class, UploadMaterialAction $action): RedirectResponse
    {
        $this->authorize('create', [Material::class, $class]);

        $action->execute($class, $request->user(), $request->file('file'), $request->validated());

        return redirect()->route('classes.materials.index', $class)->with('success', 'Material enviado com sucesso.');
    }

    public function destroy(TurmaClass $class, Material $material, DeleteMaterialAction $action): RedirectResponse
    {
        $this->authorize('delete', $material);

        $action->execute($material);

        return back()->with('success', 'Material removido com sucesso.');
    }

    public function download(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('download', $material);

        return redirect($material->download_url);
    }
}
