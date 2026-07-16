<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return response()->json([
            'stats' => $dashboard->stats($tenantId),
            'announcements' => $dashboard->latestAnnouncements($tenantId),
        ]);
    }
}
