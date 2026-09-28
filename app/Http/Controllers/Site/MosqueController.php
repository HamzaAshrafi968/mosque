<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Tenant;
use Illuminate\View\View;

class MosqueController extends Controller
{
    public function index(): View
    {
        return view('site.mosques.index', [
            'mosques' => Tenant::query()
                ->publiclyVisible()
                ->whereNotNull('code')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Tenant $mosque): View
    {
        abort_unless($mosque->isPubliclyVisible() && filled($mosque->code), 404);

        return view('site.mosques.show', [
            'mosque' => $mosque,
            'donations' => Donation::withoutGlobalScope('tenant')
                ->where('tenant_id', $mosque->id)
                ->visiblePublicly()
                ->latest()
                ->limit(9)
                ->get(),
        ]);
    }
}
