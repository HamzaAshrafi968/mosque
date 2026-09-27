<?php

namespace App\Http\Controllers\Guardian;

use App\Services\PortalNoticeSettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends BaseGuardianController
{
    public function index(Request $request, PortalNoticeSettingsService $notices): View
    {
        return view('guardian.dashboard', [
            'cards' => $this->academic->dashboardCards($this->children($request)),
            'notice' => $notices->guardianNotice(),
        ]);
    }
}
