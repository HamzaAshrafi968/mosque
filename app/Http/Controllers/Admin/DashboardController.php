<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        $tenantId = $request->user()->tenant_id;

        return view('admin.dashboard', [
            'stats' => $dashboard->stats($tenantId),
            'announcements' => $dashboard->latestAnnouncements($tenantId),
        ]);
    }
}
