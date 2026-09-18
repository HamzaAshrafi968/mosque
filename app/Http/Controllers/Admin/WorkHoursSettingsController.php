<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\WorkHoursSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «مدير الجامع → الإعدادات → ساعات العمل والرواتب»:
 * الحد الأقصى لساعات الفترة الواحدة، والتوقيت المحلي للجامع.
 */
class WorkHoursSettingsController extends Controller
{
    public function __construct(
        private readonly WorkHoursSettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.work-hours', [
            'maxSlotHours' => $this->settings->maxSlotHours(),
            'timezone' => $this->settings->timezone(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_slot_hours' => [
                'required',
                'numeric',
                'min:'.WorkHoursSettingsService::MIN_MAX_SLOT_HOURS,
                'max:'.WorkHoursSettingsService::MAX_MAX_SLOT_HOURS,
            ],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        $before = [
            'max_slot_hours' => $this->settings->maxSlotHours(),
            'timezone' => $this->settings->timezone(),
        ];

        $this->settings->setMaxSlotHours((float) $data['max_slot_hours']);
        $this->settings->setTimezone($data['timezone'] ?? null);

        $after = [
            'max_slot_hours' => $this->settings->maxSlotHours(),
            'timezone' => $this->settings->timezone(),
        ];

        $this->audit->log(
            'work_hours.settings.updated',
            'tenant_setting',
            null,
            config('app.current_tenant_id'),
            before: $before,
            after: $after,
            actor: $request->user()
        );

        return back()->with('success', 'تم حفظ إعدادات ساعات العمل');
    }
}
