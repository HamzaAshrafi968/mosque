<?php

namespace App\Services;

use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Models\Donation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PortalNotification;

/**
 * التبرعات والمساهمات: إنشاء (من الموقع العام أو يدوي من المدير)،
 * قبول/رفض مع تدقيق، وتطبيع الحقول المالية حسب النوع (المبلغ والعملة
 * للنوع المادي فقط — تُلغى ضمنيًا للعيني والمعنوي).
 */
class DonationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /** تقديم من زائر الموقع العام: دائمًا «بانتظار المراجعة». */
    public function createFromPublic(Tenant $mosque, array $data): Donation
    {
        $donation = Donation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $mosque->id,
            'type' => $data['type'],
            'custom_type' => $data['custom_type'] ?? null,
            'delivery_date' => $data['delivery_date'] ?? null,
            'status' => DonationStatus::Pending,
            'source' => 'public',
            'donor_name' => $data['donor_name'],
            'donor_phone' => $data['donor_phone'] ?? null,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
        ]);

        $this->notifyMosqueManagers($mosque, $donation);

        return $donation;
    }

    /** تسجيل يدوي من مدير الجامع: الحالة يختارها (افتراضيًا مقبول). */
    public function createByAdmin(array $data, User $actor): Donation
    {
        $donation = Donation::create([
            'type' => $data['type'],
            'custom_type' => $data['custom_type'] ?? null,
            'delivery_date' => $data['delivery_date'] ?? null,
            'status' => $data['status'] ?? DonationStatus::Accepted->value,
            'source' => 'admin',
            'donor_name' => $data['donor_name'],
            'donor_phone' => $data['donor_phone'] ?? null,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log('donation.created', 'donation', $donation->id, $donation->tenant_id, after: $donation->getAttributes(), actor: $actor);

        if ($donation->status === DonationStatus::Accepted) {
            $this->notifyMosqueAudience($donation);
        }

        return $donation;
    }

    public function update(Donation $donation, array $data, User $actor): Donation
    {
        $before = $donation->getAttributes();

        $donation->update([
            'type' => $data['type'],
            'custom_type' => $data['custom_type'] ?? null,
            'delivery_date' => $data['delivery_date'] ?? null,
            'donor_name' => $data['donor_name'],
            'donor_phone' => $data['donor_phone'] ?? null,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
        ]);

        $this->audit->log('donation.updated', 'donation', $donation->id, $donation->tenant_id, before: $before, after: $donation->getAttributes(), actor: $actor);

        return $donation;
    }

    public function accept(Donation $donation, User $actor): Donation
    {
        $before = $donation->getAttributes();

        $donation->update([
            'status' => DonationStatus::Accepted,
            'reject_reason' => null,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        $this->audit->log('donation.accepted', 'donation', $donation->id, $donation->tenant_id, before: $before, after: $donation->getAttributes(), actor: $actor);

        $this->notifyMosqueAudience($donation);

        return $donation;
    }

    public function reject(Donation $donation, ?string $reason, User $actor): Donation
    {
        $before = $donation->getAttributes();

        $donation->update([
            'status' => DonationStatus::Rejected,
            'reject_reason' => $reason,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        $this->audit->log('donation.rejected', 'donation', $donation->id, $donation->tenant_id, before: $before, after: $donation->getAttributes(), actor: $actor);

        return $donation;
    }

    public function destroy(Donation $donation, User $actor): void
    {
        $this->audit->log('donation.deleted', 'donation', $donation->id, $donation->tenant_id, before: $donation->getAttributes(), actor: $actor);

        $donation->delete();
    }

    /**
     * تطبيع حقول المساهمة: المبلغ والعملة يُحفظان للنوع المادي فقط،
     * والنوع المخصص يُحفظ فقط عند اختيار «غير ذلك».
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeFinancialFields(array $data): array
    {
        $type = DonationType::tryFrom((string) $data['type']);

        if (! $type?->requiresAmount()) {
            $data['amount'] = null;
            $data['currency'] = null;
        }

        if ($type !== DonationType::Other) {
            $data['custom_type'] = null;
        }

        return $data;
    }

    /**
     * إشعار مديري الجامع المستهدف بوصول تقديم من الموقع العام.
     * نُرسل مباشرة (لا عبر NotificationService) لأن التقديم يتم بلا سياق
     * دخول فلا يوجد نطاق جامع نشط في العملية.
     */
    private function notifyMosqueManagers(Tenant $mosque, Donation $donation): void
    {
        $managers = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $mosque->id)
            ->where('role', User::ROLE_ADMIN)
            ->get();

        foreach ($managers as $manager) {
            $manager->notify(new PortalNotification(
                'تبرع جديد بانتظار المراجعة',
                'وصلت مساهمة '.$donation->notificationSummary().' من '.$donation->displayDonorName().' — راجعها في صفحة التبرعات والمساهمات',
                route('admin.donations.index', ['status' => DonationStatus::Pending->value]),
            ));
        }
    }

    /**
     * إشعار جميع منسوبي الجامع (طلاب / أولياء أمور / أساتذة / مديرون)
     * بوصول تبرع مقبول — تصل الرسالة إلى صناديق إشعارات البوابات،
     * ويملك المدير رابطًا مباشرًا لصفحة التبرعات.
     */
    private function notifyMosqueAudience(Donation $donation): void
    {
        $users = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $donation->tenant_id)
            ->whereIn('role', [User::ROLE_STUDENT, User::ROLE_GUARDIAN, User::ROLE_TEACHER, User::ROLE_ADMIN])
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $title = 'تبرع جديد من أهل الخير';
        $body = $donation->notificationSummary().' — بإشراف إدارة الجامع';

        [$admins, $others] = $users->partition(fn (User $user) => $user->role === User::ROLE_ADMIN);

        $this->notifications->send($others, $title, $body);
        $this->notifications->send(
            $admins,
            $title,
            $body,
            route('admin.donations.index', ['status' => DonationStatus::Accepted->value]),
        );
    }
}
