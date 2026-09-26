<?php

namespace App\Http\Controllers;

use App\Services\InvoiceTrackerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private InvoiceTrackerService $tracker) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $view = match ($user->role) {
            'ADMIN' => 'dashboard.admin',
            'RESEPSIONIS' => 'dashboard.receptionist',
            'USER' => 'dashboard.user',
            'ACCOUNTING' => 'dashboard.accounting',
            default => abort(403, 'Role tidak dikenali.'),
        };

        return view($view, ['bootstrap' => $this->tracker->dashboardData($user)]);
    }
}
