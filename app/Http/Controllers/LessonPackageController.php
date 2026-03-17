<?php

namespace App\Http\Controllers;

use App\Actions\Packages\CreatePackageAction;
use App\Http\Requests\StorePackageRequest;
use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only controller for managing a student's lesson credit packages.
 *
 * Packages with existing lessons cannot be deleted (the Lesson FK uses
 * restrictOnDelete at the DB level), so the destroy method performs an
 * explicit check and returns a validation error instead of letting the
 * DB constraint fail with a cryptic error.
 *
 * @see CreatePackageAction For package creation logic
 */
class LessonPackageController extends Controller
{
    public function index(User $student): Response
    {
        $packages = $student->lessonPackages()
            ->latest()
            ->get()
            ->map(fn ($p) => array_merge($p->toArray(), [
                'remaining' => $p->remaining,
                'is_active' => $p->isActive(),
            ]));

        return Inertia::render('Packages/Index', [
            'student' => $student->only('id', 'name'),
            'packages' => $packages,
        ]);
    }

    public function store(StorePackageRequest $request, User $student, CreatePackageAction $action): RedirectResponse
    {
        $action->execute($student, $request->validated());

        return back()->with('success', 'Pacote adicionado com sucesso.');
    }

    public function destroy(User $student, LessonPackage $package): RedirectResponse
    {
        // Cannot delete a package that has lessons recorded against it
        if ($package->lessons()->exists()) {
            return back()->withErrors(['package' => 'Não é possível excluir um pacote com aulas registradas.']);
        }

        $package->delete();

        return back()->with('success', 'Pacote removido com sucesso.');
    }
}
