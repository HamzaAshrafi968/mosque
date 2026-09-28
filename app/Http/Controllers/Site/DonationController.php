<?php

namespace App\Http\Controllers\Site;

use App\Enums\DonationCurrency;
use App\Enums\DonationType;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\DonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException; /**
 * استقبال تبرعات زوار الموقع العام (بلا تسجيل دخول):
 * يختار الزائر الجامع المستهدف، وتُحفظ المساهمة «بانتظار المراجعة»
 * حتى يقبلها مدير الجامع فتظهر في الصفحة العامة.
 */
class DonationController extends Controller
{
    public function store(Request $request, DonationService $donations): RedirectResponse
    {
        $type = DonationType::tryFrom((string) $request->input('type'));

        $data = $request->validate([
            'mosque_id' => ['required', 'string'],
            'type' => ['required', Rule::enum(DonationType::class)],
            'custom_type' => [$type === DonationType::Other ? 'required' : 'nullable', 'string', 'max:80'],
            'donor_name' => ['required', 'string', 'max:120'],
            'donor_phone' => ['required', 'string', 'max:30'],
            'delivery_date' => ['required', 'date'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => [
                $type?->requiresAmount() ? 'required' : 'nullable',
                'numeric',
                'min:1',
                'max:999999999',
            ],
            'currency' => [
                $type?->requiresAmount() ? 'required' : 'nullable',
                Rule::enum(DonationCurrency::class),
            ],
        ]);

        $mosque = Tenant::query()
            ->publiclyVisible()
            ->whereNotNull('code')
            ->whereKey($request->input('mosque_id'))
            ->first();

        if ($mosque === null) {
            throw ValidationException::withMessages([
                'mosque_id' => 'الجامع المحدد غير متاح حاليًا',
            ]);
        }

        $donations->createFromPublic($mosque, $donations->normalizeFinancialFields($data));

        return redirect()->back()->with('success', 'شكرًا لك! وصلت مساهمتك إلى إدارة الجامع وستظهر للجميع بعد الموافقة عليها.');
    }
}
