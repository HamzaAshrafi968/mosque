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
 * حد النجاح الموحّد لجميع اختبارات القرآن (minimum passing percentage)
 * + الاعتماد التلقائي للحافظ عند إتمام 30 جزءاً.
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
            'autoConfirmCompletion' => $this->settings->autoConfirmCompletion(),
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
            'auto_confirm_completion' => ['nullable', 'boolean'],
        ]);

        $autoConfirm = $request->boolean('auto_confirm_completion');

        $before = [
            'minimum_passing_percentage' => $this->settings->minimumPassingPercentage(),
            'auto_confirm_completion' => $this->settings->autoConfirmCompletion(),
        ];

        $this->settings->setMinimumPassingPercentage((float) $data['minimum_passing_percentage']);
        $this->settings->setAutoConfirmCompletion($autoConfirm);

        $this->audit->log(
            'quran.settings.updated',
            'tenant_setting',
            null,
            config('app.current_tenant_id'),
            before: $before,
            after: [
                'minimum_passing_percentage' => (float) $data['minimum_passing_percentage'],
                'auto_confirm_completion' => $autoConfirm,
            ],
            actor: $request->user()
        );

        return back()->with('success', 'تم حفظ إعدادات برنامج القرآن');
    }
}
