<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\PortalNoticeSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «مدير الجامع → الإعدادات → إعلان بوابة أولياء الأمور»:
 * نص ثابت يظهر في أعلى الصفحة الرئيسية لبوابة ولي الأمر (نص فارغ = مخفي).
 */
class PortalNoticeSettingsController extends Controller
{
    public function __construct(
        private readonly PortalNoticeSettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.portal-notice', [
            'notice' => $this->settings->guardianNotice(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notice' => ['nullable', 'string', 'max:'.PortalNoticeSettingsService::MAX_LENGTH],
        ]);

        $before = ['notice' => $this->settings->guardianNotice()];

        $this->settings->setGuardianNotice($data['notice'] ?? null);

        $this->audit->log(
            'portal_notice.updated',
            'tenant_setting',
            null,
            config('app.current_tenant_id'),
            before: $before,
            after: ['notice' => $this->settings->guardianNotice()],
            actor: $request->user()
        );

        return back()->with('success', 'تم حفظ إعلان بوابة أولياء الأمور');
    }
}
