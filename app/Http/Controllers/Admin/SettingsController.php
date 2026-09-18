<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardPointRule;
use App\Services\AuthorizationService;
use App\Services\QuranSettingsService;
use App\Services\RewardPointSettingsService;
use App\Services\WorkHoursSettingsService;
use Illuminate\View\View;

/**
 * «مدير الجامع → مركز الإعدادات»: بوابة واحدة تجمع إعدادات برنامج القرآن
 * ونقاط المكافآت والصلاحيات والدوامات والبرامج، مع بطاقة حالة لكل قسم.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly QuranSettingsService $quranSettings,
        private readonly RewardPointSettingsService $rewardSettings,
        private readonly WorkHoursSettingsService $workHoursSettings,
        private readonly AuthorizationService $authorization,
    ) {}

    public function index(): View
    {
        $user = auth()->user();

        $can = fn (string $permission) => $this->authorization->can($user, $permission);

        $activeRules = RewardPointRule::query()
            ->where('points', '>', 0)
            ->count();

        return view('admin.settings.index', [
            'minimumPassingPercentage' => $this->quranSettings->minimumPassingPercentage(),
            'automaticEnabled' => $this->rewardSettings->isEnabled(),
            'activeRules' => $activeRules,
            'canQuranSettings' => $can('quran_settings.view'),
            'canUsers' => $can('users.view'),
            'canSessions' => $can('sessions.view'),
            'canPrograms' => $can('programs.view'),
            'canWorkHours' => $can('work_hours.view'),
            'maxSlotHours' => $this->workHoursSettings->maxSlotHours(),
        ]);
    }
}
