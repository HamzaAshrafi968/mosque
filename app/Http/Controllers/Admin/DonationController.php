<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DonationCurrency;
use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Services\DonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * التبرعات والمساهمات: مراجعة تقديمات الموقع العام (قبول/رفض)
 * وتسجيل تبرعات واردة يدويًا من مدير الجامع.
 */
class DonationController extends Controller
{
    public function __construct(private readonly DonationService $donations) {}

    public function index(Request $request): View
    {
        $status = DonationStatus::tryFrom((string) $request->query('status', DonationStatus::Pending->value));
        $type = DonationType::tryFrom((string) $request->query('type', ''));

        $donations = Donation::query()
            ->status($status)
            ->type($type)
            ->with(['reviewer:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.donations.index', [
            'donations' => $donations,
            'status' => $status,
            'type' => $type,
            'statusCounts' => Donation::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'totalCount' => Donation::query()->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.donations.create', [
            'types' => DonationType::cases(),
            'currencies' => DonationCurrency::cases(),
            'statuses' => DonationStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));

        $this->donations->createByAdmin($this->donations->normalizeFinancialFields($data), $request->user());

        return redirect()->route('admin.donations.index')->with('success', 'تم تسجيل التبرع/المساهمة');
    }

    public function edit(Donation $donation): View
    {
        return view('admin.donations.edit', [
            'donation' => $donation,
            'types' => DonationType::cases(),
            'currencies' => DonationCurrency::cases(),
        ]);
    }

    public function update(Request $request, Donation $donation): RedirectResponse
    {
        $data = $request->validate($this->rules($request));

        $this->donations->update($donation, $this->donations->normalizeFinancialFields($data), $request->user());

        return redirect()->route('admin.donations.index')->with('success', 'تم تعديل التبرع/المساهمة');
    }

    public function accept(Request $request, Donation $donation): RedirectResponse
    {
        $this->donations->accept($donation, $request->user());

        return back()->with('success', 'تم قبول التبرع — أصبح ظاهرًا في الموقع العام');
    }

    public function reject(Request $request, Donation $donation): RedirectResponse
    {
        $data = $request->validate([
            'reject_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->donations->reject($donation, $data['reject_reason'] ?? null, $request->user());

        return back()->with('success', 'تم رفض التبرع');
    }

    public function destroy(Request $request, Donation $donation): RedirectResponse
    {
        $this->donations->destroy($donation, $request->user());

        return back()->with('success', 'تم حذف التبرع/المساهمة');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request): array
    {
        $type = DonationType::tryFrom((string) $request->input('type'));

        return [
            'type' => ['required', Rule::enum(DonationType::class)],
            'custom_type' => [$type === DonationType::Other ? 'required' : 'nullable', 'string', 'max:80'],
            'status' => ['sometimes', Rule::enum(DonationStatus::class)],
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
        ];
    }
}
