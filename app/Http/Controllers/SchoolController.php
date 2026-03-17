<?php
namespace App\Http\Controllers;
use App\Actions\Schools\CreateSchoolAction;
use App\Actions\Schools\UpdateSchoolAction;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only CRUD controller for managing schools (tenants).
 *
 * Schools are the multi-tenancy root entity. The index view includes a user
 * count per school for quick overview. Deletion does not cascade-check for
 * dependent users -- consider adding a guard if needed.
 */
class SchoolController extends Controller
{
    public function index(): Response
    {
        $schools = School::withCount('users')->latest()->get();
        return Inertia::render('Schools/Index', ['schools' => $schools]);
    }

    public function create(): Response
    {
        return Inertia::render('Schools/Create');
    }

    public function store(StoreSchoolRequest $request, CreateSchoolAction $action): RedirectResponse
    {
        $action->execute($request->validated());
        return redirect()->route('admin.schools.index')->with('success', 'Escola criada com sucesso.');
    }

    public function edit(School $school): Response
    {
        return Inertia::render('Schools/Edit', ['school' => $school]);
    }

    public function update(UpdateSchoolRequest $request, School $school, UpdateSchoolAction $action): RedirectResponse
    {
        $action->execute($school, $request->validated());
        return redirect()->route('admin.schools.index')->with('success', 'Escola atualizada com sucesso.');
    }

    public function destroy(School $school): RedirectResponse
    {
        $school->delete();
        return redirect()->route('admin.schools.index')->with('success', 'Escola removida com sucesso.');
    }
}
