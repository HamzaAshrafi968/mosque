<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardPoint;
use App\Models\RewardPointRule;
use App\Models\StudySession;
use App\Services\AuditLogger;
use App\Services\RewardPointSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * «مدير الجامع → الإعدادات → نقاط المكافآت»:
 *
 * - مفتاح عام لتشغيل/إيقاف المنح التلقائي.
 * - لكل دوام قواعده الخاصة: حفظ الصفحات الجديدة (بعدد صفحات قابل للتعديل)،
 *   إتمام الخمسات، اجتياز اختبار الدفعة، إتمام خطة الاستماع، وحفظ الدورة
 *   الشرعية كاملاً. القيمة فارغة/صفر أو المفتاح مُطفأ = القاعدة معطّلة.
 */
class RewardPointSettingsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RewardPointSettingsService $settings,
    ) {}

    public function edit(): View
    {
        $sessions = StudySession::query()->orderBy('name')->get();

        $rulesBySession = [];

        foreach ($sessions as $session) {
            foreach (RewardPointRule::TYPES as $type) {
                $rulesBySession[$session->id][$type] = ['pages_count' => null, 'points' => null];
            }
        }

        RewardPointRule::query()
            ->whereIn('study_session_id', $sessions->pluck('id'))
            ->get()
            ->each(function (RewardPointRule $rule) use (&$rulesBySession) {
                if (isset($rulesBySession[$rule->study_session_id][$rule->rule_type])) {
                    $rulesBySession[$rule->study_session_id][$rule->rule_type] = [
                        'pages_count' => $rule->pages_count,
                        'points' => $rule->points,
                    ];
                }
            });

        return view('admin.settings.rewards', [
            'sessions' => $sessions,
            'rulesBySession' => $rulesBySession,
            'definitions' => RewardPointRule::definitions(),
            'automaticEnabled' => $this->settings->isEnabled(),
            'totals' => $this->automaticTotals($sessions->pluck('id')->all()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'automatic_enabled' => ['nullable', 'boolean'],
            'rules' => ['nullable', 'array'],
            'rules.*.tasmee_pages.pages_count' => ['nullable', 'integer', 'min:1', 'max:604'],
        ];

        foreach (RewardPointRule::TYPES as $type) {
            $rules["rules.*.{$type}.enabled"] = ['nullable', 'boolean'];
            $rules["rules.*.{$type}.points"] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        $data = $request->validate($rules);

        $sessions = StudySession::query()->pluck('id')->all();
        $submitted = $data['rules'] ?? [];

        foreach ($submitted as $sessionId => $ruleSet) {
            if (! in_array($sessionId, $sessions, true)) {
                continue;
            }

            $pages = $ruleSet['tasmee_pages'] ?? [];

            if ($this->ruleEnabled($pages) && (int) ($pages['points'] ?? 0) > 0 && blank($pages['pages_count'] ?? null)) {
                throw ValidationException::withMessages([
                    "rules.{$sessionId}.tasmee_pages.pages_count" => 'عدد الصفحات مطلوب عند تفعيل نقاط الحفظ',
                ]);
            }
        }

        $before = $this->snapshot();

        DB::transaction(function () use ($submitted, $sessions, $data) {
            if (array_key_exists('automatic_enabled', $data)) {
                $this->settings->setEnabled((bool) $data['automatic_enabled']);
            }

            foreach ($submitted as $sessionId => $ruleSet) {
                if (! in_array($sessionId, $sessions, true)) {
                    continue;
                }

                foreach (RewardPointRule::TYPES as $type) {
                    $this->saveRule($sessionId, $type, $ruleSet[$type] ?? []);
                }
            }
        });

        $this->audit->log(
            'reward_points.settings.updated',
            'reward_point_rule',
            null,
            config('app.current_tenant_id'),
            before: $before,
            after: $this->snapshot(),
            actor: $request->user()
        );

        return back()->with('success', 'تم حفظ إعدادات نقاط المكافآت');
    }

    /**
     * القاعدة مفعّلة إذا وُجد مفتاح enabled وكان مفتوحاً، أو غاب المفتاح
     * (توافق خلفي) وكان لها نقاط أكبر من صفر.
     *
     * @param  array{enabled?: mixed, points?: mixed}  $data
     */
    private function ruleEnabled(array $data): bool
    {
        if (array_key_exists('enabled', $data)) {
            return (bool) $data['enabled'];
        }

        return (int) ($data['points'] ?? 0) > 0;
    }

    /** @param  array{enabled?: mixed, points?: mixed, pages_count?: mixed}  $data */
    private function saveRule(string $sessionId, string $type, array $data): void
    {
        $points = $data['points'] ?? null;
        $points = $points === null || $points === '' ? null : (int) $points;

        $query = RewardPointRule::query()
            ->where('study_session_id', $sessionId)
            ->where('rule_type', $type);

        if (! $this->ruleEnabled($data) || $points === null || $points <= 0) {
            $query->delete();

            return;
        }

        $payload = ['points' => $points];

        if ($type === RewardPointRule::TYPE_TASMEE_PAGES) {
            $pages = $data['pages_count'] ?? null;
            $payload['pages_count'] = $pages === null || $pages === '' ? null : (int) $pages;
        }

        $rule = $query->first() ?? new RewardPointRule([
            'study_session_id' => $sessionId,
            'rule_type' => $type,
        ]);

        $rule->fill($payload)->save();
    }

    /**
     * إجمالي النقاط الممنوحة تلقائياً لكل دوام (للعرض فقط).
     *
     * @param  array<int, string>  $sessionIds
     * @return array<string, array{awards: int, points: int}>
     */
    private function automaticTotals(array $sessionIds): array
    {
        return RewardPoint::query()
            ->whereIn('study_session_id', $sessionIds)
            ->whereNotNull('source_type')
            ->groupBy('study_session_id')
            ->selectRaw('study_session_id, count(*) as awards, sum(points) as points')
            ->get()
            ->mapWithKeys(fn (RewardPoint $row) => [
                (string) $row->study_session_id => [
                    'awards' => (int) $row->awards,
                    'points' => (int) $row->points,
                ],
            ])
            ->all();
    }

    /** @return array{enabled: bool, rules: array<string, array{pages_count: ?int, points: ?int}>} */
    private function snapshot(): array
    {
        return [
            'enabled' => $this->settings->isEnabled(),
            'rules' => RewardPointRule::query()
                ->get()
                ->mapWithKeys(fn (RewardPointRule $rule) => [
                    $rule->study_session_id.':'.$rule->rule_type => [
                        'pages_count' => $rule->pages_count,
                        'points' => $rule->points,
                    ],
                ])
                ->all(),
        ];
    }
}
