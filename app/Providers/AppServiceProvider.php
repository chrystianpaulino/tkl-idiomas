<?php

namespace App\Providers;

use App\Models\ExerciseList;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\ScheduledLesson;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use App\Policies\ClassPolicy;
use App\Policies\ExerciseListPolicy;
use App\Policies\LessonPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ScheduledLessonPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SchoolPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

/**
 * Main application service provider.
 *
 * IMPORTANT: Policy registration is MANUAL in this project. Laravel's auto-discovery
 * is NOT active. Every new policy MUST be explicitly registered here via Gate::policy(),
 * or any authorize()/can() call involving that model will throw an
 * InvalidArgumentException at runtime.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services: Vite prefetching, global authorization
     * shortcut, and manual policy registration.
     *
     * The Gate::before hook centralises the super_admin bypass that previously
     * existed in every individual policy. Returning null (not false) keeps the
     * normal policy chain running for non-super_admins. Returning false would
     * BLOCK every ability for the user, including those a downstream policy
     * would otherwise grant.
     *
     * When adding a new Policy, add a Gate::policy() call here. Forgetting to do so
     * will cause runtime errors on any authorization check for that model.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Strict password policy applied wherever Password::defaults() is used
        // (registration, password reset, user creation, school provisioning,
        // password update). 12 chars + mixed case + numbers + symbols. The
        // ->uncompromised() check (haveibeenpwned API call) is enabled only in
        // production to keep tests and dev fast and offline. The seeders set
        // 'password' for convenience accounts via Hash::make directly, which
        // does not pass through the validator -- so dev usability stays.
        Password::defaults(function () {
            $rule = Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols();

            return app()->environment('production')
                ? $rule->uncompromised()
                : $rule;
        });

        // Global super_admin bypass: applies to every Gate::allows / $user->can
        // call before per-policy methods run. Per-policy `before()` hooks are
        // therefore redundant for super_admin and have been removed -- their
        // remaining responsibilities (e.g. PaymentPolicy granting all abilities
        // to admins) stay in the policy itself.
        Gate::before(function ($user, string $ability): ?bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        // Manual policy registration (auto-discovery is NOT active)
        Gate::policy(TurmaClass::class, ClassPolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(Material::class, MaterialPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(ExerciseList::class, ExerciseListPolicy::class);
        Gate::policy(Schedule::class, SchedulePolicy::class);
        Gate::policy(ScheduledLesson::class, ScheduledLessonPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(School::class, SchoolPolicy::class);
    }
}
