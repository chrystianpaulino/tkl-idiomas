<?php

namespace App\Http\Controllers;

use App\Actions\Users\InviteUserAction;
use App\Actions\Users\ResendInviteAction;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only CRUD controller for managing platform users.
 *
 * Authorization is delegated to UserPolicy (registered in AppServiceProvider).
 * The global Gate::before hook grants every ability to super_admin first, so
 * the policy methods only need to encode school_admin / professor / aluno
 * rules. school_admin sees and edits users within their own school only;
 * editing other admins or super_admin is forbidden by the policy.
 *
 * The update method directly sets the role attribute (bypassing $fillable)
 * because role is intentionally excluded from mass assignment. This is one
 * of two places role is set (the other is InviteUserAction).
 *
 * Wave 9: store() now delegates to InviteUserAction, which creates the user
 * in "pending invite" state and dispatches an email with a one-time link
 * the recipient uses to define their own password.
 *
 * @see UserPolicy
 * @see InviteUserAction For user creation with invite email dispatch
 * @see ResendInviteAction For reissuing an invite that expired/was lost
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        $users = User::query()
            ->when(! $actor->isSuperAdmin(), fn ($q) => $q->where('school_id', $actor->school_id))
            ->when($request->input('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->input('search'), fn ($q, $s) => $q->where(function ($q2) use ($s) {
                $q2->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%");
            }))
            ->paginate(20)
            ->withQueryString()
            // Map a derived has_pending_invite flag onto the paginator items
            // so the Inertia page can show a "Reenviar convite" button. Done
            // server-side because invite_token is hidden via $hidden on the
            // model -- the frontend never sees the hash itself.
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'has_pending_invite' => $user->hasPendingInvite(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['role', 'search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create');
    }

    public function store(StoreUserRequest $request, InviteUserAction $action): RedirectResponse
    {
        // school_admin: invitee inherits the actor's school. super_admin
        // (granted via Gate::before in AppServiceProvider) has school_id = null,
        // which is forwarded as-is so the invitee starts unscoped -- super_admin
        // can later attach them to a school via update.
        $user = $action->execute(
            $request->validated(),
            $request->user(),
            $request->user()->school_id,
        );

        // The invite event is already audited inside InviteUserAction. We
        // additionally emit user.created here so reports that group user
        // lifecycle events keep working unchanged after the invite refactor.
        Audit::log('user.created', [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'target_role' => $user->role,
            'target_school_id' => $user->school_id,
        ]);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Convite enviado para '.$user->email.'.');
    }

    public function resendInvite(User $user, ResendInviteAction $action): RedirectResponse
    {
        $this->authorize('resendInvite', $user);

        $action->execute($user);

        return redirect()->back()->with('success', 'Convite reenviado para '.$user->email.'.');
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

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
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'user' => $user->only('id', 'name', 'email', 'role'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $oldRole = $user->role;

        $user->name = $request->validated('name');
        $user->email = $request->validated('email');
        $user->role = $request->validated('role');
        $user->save();

        // Field-level audit: capture which attributes actually changed.
        // Sensitive fields (password, remember_token) are filtered by the
        // Audit facade itself, but this endpoint never touches them anyway.
        $changedFields = array_keys($user->getChanges());
        if ($changedFields !== []) {
            Audit::log('user.updated', [
                'target_user_id' => $user->id,
                'target_email' => $user->email,
                'changed_fields' => $changedFields,
            ]);
        }

        // Distinct event for role transitions: makes privilege-escalation
        // attempts trivially greppable in the audit log.
        if ($oldRole !== $user->role) {
            Audit::log('user.role_changed', [
                'target_user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $user->role,
            ]);
        }

        return redirect()->route('admin.users.show', $user)->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        // Capture identity BEFORE delete: after $user->delete() the in-memory
        // model still has its attributes, but pulling them eagerly keeps the
        // audit payload self-contained even if a future refactor swaps in a
        // forceDelete pattern that null-resets attributes.
        $audit = [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'target_role' => $user->role,
            'target_school_id' => $user->school_id,
        ];

        $user->delete();

        Audit::log('user.deleted', $audit);

        return redirect()->route('admin.users.index')->with('success', 'Usuário removido com sucesso.');
    }
}
