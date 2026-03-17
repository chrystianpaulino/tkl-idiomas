<?php

namespace App\Http\Controllers;

use App\Actions\Users\CreateUserAction;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->when($request->input('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['role', 'search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create');
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $user = $action->execute($request->validated());

        return redirect()->route('admin.users.show', $user)->with('success', 'Usuário criado com sucesso.');
    }

    public function show(User $user): Response
    {
        $packages = $user->lessonPackages()
            ->latest()
            ->get()
            ->map(fn ($p) => array_merge($p->toArray(), [
                'remaining' => $p->remaining,
                'is_active' => $p->isActive(),
            ]));

        $recentLessons = $user->lessons()
            ->with(['turmaClass', 'professor'])
            ->latest('conducted_at')
            ->limit(10)
            ->get();

        return Inertia::render('Users/Show', [
            'user' => $user->only('id', 'name', 'email', 'role'),
            'packages' => $packages,
            'recentLessons' => $recentLessons,
            'enrolledClasses' => $user->enrolledClasses()->get(['classes.id', 'name']),
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => $user->only('id', 'name', 'email', 'role'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->name = $request->validated('name');
        $user->email = $request->validated('email');
        $user->role = $request->validated('role');
        $user->save();

        return redirect()->route('admin.users.show', $user)->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuário removido com sucesso.');
    }
}
