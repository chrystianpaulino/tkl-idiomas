<?php

namespace App\Http\Controllers;

use App\Actions\Schools\ProvisionSchoolAction;
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
    private function schoolsIndexRoute(): string
    {
        return auth()->user()->isSuperAdmin() ? 'platform.schools.index' : 'admin.schools.index';
    }

    public function index(): Response
    {
        $query = School::withCount('users')->latest();

        if (! auth()->user()->isSuperAdmin()) {
            $query->where('id', auth()->user()->school_id);
        }

        $schools = $query->get();

        return Inertia::render('Schools/Index', ['schools' => $schools]);
    }

    public function create(): Response
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        return Inertia::render('Schools/Create');
    }

    public function store(StoreSchoolRequest $request, ProvisionSchoolAction $action): RedirectResponse
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $result = $action->execute($request->validated());

        return redirect()->route($this->schoolsIndexRoute())->with(
            'success',
            "Escola \"{$result['school']->name}\" criada com administrador {$result['admin']->email}."
        );
    }

    public function edit(School $school): Response
    {
        if (! auth()->user()->isSuperAdmin() && auth()->user()->school_id !== $school->id) {
            abort(403);
        }

        return Inertia::render('Schools/Edit', ['school' => $school]);
    }

    public function update(UpdateSchoolRequest $request, School $school, UpdateSchoolAction $action): RedirectResponse
    {
        if (! auth()->user()->isSuperAdmin() && auth()->user()->school_id !== $school->id) {
            abort(403);
        }

        $action->execute($school, $request->validated());

        return redirect()->route($this->schoolsIndexRoute())->with('success', 'Escola atualizada com sucesso.');
    }

    public function destroy(School $school): RedirectResponse
    {
        // L3: Only super_admin may delete schools
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $school->delete();

        return redirect()->route($this->schoolsIndexRoute())->with('success', 'Escola removida com sucesso.');
    }
}
