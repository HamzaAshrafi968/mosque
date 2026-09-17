<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardPointRule;
use App\Models\StudySession;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * «مدير الجامع → الإعدادات → نقاط المكافآت»:
 * لكل دوام قواعده الخاصة: نقاط حفظ الصفحات الجديدة (بعدد صفحات قابل للتعديل)،
 * نقاط إتمام الخمسات، ونقاط اجتياز اختبار الدفعة. القيمة فارغة/صفر = معطّل.
 */
class RewardPointSettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

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
                $rulesBySession[$rule->study_session_id][$rule->rule_type] = [
                    'pages_count' => $rule->pages_count,
                    'points' => $rule->points,
                ];
            });

        return view('admin.settings.rewards', [
            'sessions' => $sessions,
            'rulesBySession' => $rulesBySession,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['nullable', 'array'],
            'rules.*.tasmee_pages.pages_count' => ['nullable', 'integer', 'min:1', 'max:604'],
            'rules.*.tasmee_pages.points' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'rules.*.khamsa_review.points' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'rules.*.test_pass.points' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $sessions = StudySession::query()->pluck('id')->all();
        $submitted = $data['rules'] ?? [];

        foreach ($submitted as $sessionId => $ruleSet) {
            if (! in_array($sessionId, $sessions, true)) {
                continue;
            }

            $pagesPoints = (int) ($ruleSet['tasmee_pages']['points'] ?? 0);
            $pagesCount = $ruleSet['tasmee_pages']['pages_count'] ?? null;

            if ($pagesPoints > 0 && ($pagesCount === null || $pagesCount === '')) {
                throw ValidationException::withMessages([
                    "rules.{$sessionId}.tasmee_pages.pages_count" => 'عدد الصفحات مطلوب عند تفعيل نقاط الحفظ',
                ]);
            }
        }

        $before = $this->snapshot();

        foreach ($submitted as $sessionId => $ruleSet) {
            if (! in_array($sessionId, $sessions, true)) {
                continue;
            }

            $this->saveRule($sessionId, RewardPointRule::TYPE_TASMEE_PAGES, $ruleSet['tasmee_pages'] ?? []);
            $this->saveRule($sessionId, RewardPointRule::TYPE_KHAMSA_REVIEW, $ruleSet['khamsa_review'] ?? []);
            $this->saveRule($sessionId, RewardPointRule::TYPE_TEST_PASS, $ruleSet['test_pass'] ?? []);
        }

        $this->audit->log(
            'reward_points.settings.updated',
            'reward_point_rule',
            null,
            config('app.current_tenant_id'),
            before: $before,
            after: $this->snapshot(),
            actor: $request->user()
        );

        return back()->with('success', 'تم حفظ قواعد نقاط المكافآت');
    }

    /** @param  array{pages_count?: int|string|null, points?: int|string|null}  $data */
    private function saveRule(string $sessionId, string $type, array $data): void
    {
        $points = $data['points'] ?? null;
        $points = $points === null || $points === '' ? null : (int) $points;

        $query = RewardPointRule::query()
            ->where('study_session_id', $sessionId)
            ->where('rule_type', $type);

        if ($points === null || $points === 0) {
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

    /** @return array<string, array{pages_count: ?int, points: ?int}> */
    private function snapshot(): array
    {
        return RewardPointRule::query()
            ->get()
            ->mapWithKeys(fn (RewardPointRule $rule) => [
                $rule->study_session_id.':'.$rule->rule_type => [
                    'pages_count' => $rule->pages_count,
                    'points' => $rule->points,
                ],
            ])
            ->all();
    }
}
