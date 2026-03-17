<?php

namespace App\Http\Controllers;

use App\Actions\GetDashboardStatsAction;
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
     */
    public function __invoke(Request $request, GetDashboardStatsAction $action): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => $action->execute($request->user()),
        ]);
    }
}
