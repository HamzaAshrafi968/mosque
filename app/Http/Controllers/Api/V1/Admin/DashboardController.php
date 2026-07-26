<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function index(Request $request, DashboardService $dashboard): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return $this->success([
            'stats' => $dashboard->stats($tenantId),
            'announcements' => $dashboard->latestAnnouncements($tenantId),
        ]);
    }
}
