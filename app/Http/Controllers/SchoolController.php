<?php

namespace App\Http\Controllers;

use App\Actions\Schools\ProvisionSchoolAction;
use App\Actions\Schools\UpdateSchoolAction;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\School;
use App\Policies\SchoolPolicy;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only CRUD controller for managing schools (tenants).
 *
 * Authorization is delegated to SchoolPolicy (registered in AppServiceProvider).
 * The global Gate::before hook grants every ability to super_admin first; the
 * policy methods only encode school_admin restrictions:
 *   - school_admin can view/update their OWN school only.
 *   - school_admin cannot create or delete any school (super_admin reserved).
 *
 * Schools are the multi-tenancy root entity. The index view includes a user
 * count per school for quick overview. Deletion cascades through tenant data
 * via School::booted(), which is why delete() is gated to super_admin only.
 *
 * @see SchoolPolicy
 */
class SchoolController extends Controller
{
    private function schoolsIndexRoute(): string
    {
        return auth()->user()->isSuperAdmin() ? 'platform.schools.index' : 'admin.schools.index';
    }

    public function index(): Response
    {
        $this->authorize('viewAny', School::class);

        $query = School::withCount('users')->latest();

        if (! auth()->user()->isSuperAdmin()) {
            $query->where('id', auth()->user()->school_id);
        }

        $schools = $query->get();

        return Inertia::render('Schools/Index', ['schools' => $schools]);
    }

    public function create(): Response
    {
        $this->authorize('create', School::class);

        return Inertia::render('Schools/Create');
    }

    public function store(StoreSchoolRequest $request, ProvisionSchoolAction $action): RedirectResponse
    {
        // $request->validated() already includes the uploaded file when present
        // (logo is a validated key), but we re-inject the UploadedFile instance
        // explicitly to keep the action contract clear and decoupled from
        // FormRequest internals.
        $payload = $request->validated();
        if ($request->hasFile('logo')) {
            $payload['logo'] = $request->file('logo');
        }

        $result = $action->execute($payload);

        return redirect()->route($this->schoolsIndexRoute())->with(
            'success',
            "Escola \"{$result['school']->name}\" criada com administrador {$result['admin']->email}."
        );
    }

    public function edit(School $school): Response
    {
        $this->authorize('update', $school);

        return Inertia::render('Schools/Edit', ['school' => $school]);
    }

    public function update(UpdateSchoolRequest $request, School $school, UpdateSchoolAction $action): RedirectResponse
    {
        $payload = $request->validated();
        if ($request->hasFile('logo')) {
            $payload['logo'] = $request->file('logo');
        }

        $action->execute($school, $payload);

        return redirect()->route($this->schoolsIndexRoute())->with('success', 'Escola atualizada com sucesso.');
    }

    public function destroy(School $school): RedirectResponse
    {
        $this->authorize('delete', $school);

        // Capture identity BEFORE delete: School::booted() cascades through
        // every tenant-scoped table, so the audit entry must be assembled
        // before any of that side-effect chain runs.
        $audit = [
            'school_id' => $school->id,
            'slug' => $school->slug,
            'name' => $school->name,
        ];

        $school->delete();

        Audit::log('school.deleted', $audit);

        return redirect()->route($this->schoolsIndexRoute())->with('success', 'Escola removida com sucesso.');
    }
}
