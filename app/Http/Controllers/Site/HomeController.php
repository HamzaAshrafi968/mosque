<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user !== null) {
            return redirect()->route(match (true) {
                $user->isSuperAdmin() => 'super-admin.dashboard',
                $user->isAdmin() => 'admin.dashboard',
                $user->isGuardian() => 'guardian.dashboard',
                $user->isStudent() => 'student.dashboard',
                default => 'teacher.dashboard',
            });
        }

        return view('site.home', [
            'mosques' => Tenant::query()
                ->publiclyVisible()
                ->whereNotNull('code')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
