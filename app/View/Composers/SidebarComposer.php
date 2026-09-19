<?php

namespace App\View\Composers;

use App\Models\Program;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\View\View;

/**
 * يزوّد القائمة الجانبية ببرامج الجامع المفعّلة كروابط فرعية تحت
 * «البرامج والتخصصات» — لمدير الجامع أو مدير الجوامع داخل سياق جامع.
 */
class SidebarComposer
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $inMosqueContext = $user->isSuperAdmin() && session('super_admin_mosque_id');

        if (! $user->isAdmin() && ! $inMosqueContext) {
            return;
        }

        // بدون جامع محدد لا يُفلتر نطاق العزل (يمرّ كل البرامج)، فنكتفي بعدم الجلب.
        if (config('app.current_tenant_id') === null) {
            return;
        }

        if (! $this->authorization->can($user, 'programs.view')) {
            return;
        }

        $view->with('sidebarPrograms', Program::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'type']));
    }
}
