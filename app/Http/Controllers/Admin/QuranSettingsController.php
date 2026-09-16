<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\QuranSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «مدير الجامع → الإعدادات → برنامج القرآن»:
 * حد النجاح في اختبار دفعات الحفظ (minimum passing percentage).
 */
class QuranSettingsController extends Controller
{
    public function __construct(
        private readonly QuranSettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.quran', [
            'minimumPassingPercentage' => $this->settings->minimumPassingPercentage(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'minimum_passing_percentage' => [
                'required',
                'numeric',
                'min:'.QuranSettingsService::MIN_PASSING_PERCENTAGE,
                'max:'.QuranSettingsService::MAX_PASSING_PERCENTAGE,
            ],
        ]);

        $before = $this->settings->minimumPassingPercentage();

        $this->settings->setMinimumPassingPercentage((float) $data['minimum_passing_percentage']);

        $this->audit->log(
            'quran.settings.updated',
            'tenant_setting',
            null,
            config('app.current_tenant_id'),
            before: ['minimum_passing_percentage' => $before],
            after: ['minimum_passing_percentage' => (float) $data['minimum_passing_percentage']],
            actor: $request->user()
        );

        return back()->with('success', 'تم حفظ إعدادات برنامج القرآن');
    }
}
