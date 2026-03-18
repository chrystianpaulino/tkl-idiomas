<?php

namespace App\Http\Controllers;

use App\Actions\GetDashboardStatsAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single-action controller for the main dashboard page.
 *
 * Delegates all logic to GetDashboardStatsAction, which returns role-specific
 * statistics (admin/professor/student). The controller is intentionally thin.
 */
class DashboardController extends Controller
{
    /**
     * Render the role-aware dashboard with aggregated statistics.
     *
     * Super admins have a dedicated platform dashboard — redirect them there so
     * the generic /dashboard is only ever reached by school-level users.
     */
    public function __invoke(Request $request, GetDashboardStatsAction $action): Response|RedirectResponse
    {
        if ($request->user()->isSuperAdmin()) {
            return redirect()->route('platform.dashboard');
        }

        return Inertia::render('Dashboard', [
            'stats' => $action->execute($request->user()),
        ]);
    }
}
