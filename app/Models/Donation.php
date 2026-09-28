<?php

namespace App\Models;

use App\Enums\DonationCurrency;
use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تبرع/مساهمة موجه لجامع: مادي (مبلغ + عملة) أو عيني (سلع/خدمات)
 * أو معنوي (وقت/تطوع/مهارات). لا يظهر في الموقع العام إلا بعد قبول المدير.
 */
class Donation extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'type',
        'custom_type',
        'delivery_date',
        'status',
        'source',
        'donor_name',
        'donor_phone',
        'is_anonymous',
        'title',
        'description',
        'amount',
        'currency',
        'reject_reason',
        'reviewed_by',
        'reviewed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => DonationType::class,
            'status' => DonationStatus::class,
            'currency' => DonationCurrency::class,
            'amount' => 'decimal:2',
            'is_anonymous' => 'boolean',
            'reviewed_at' => 'datetime',
            'delivery_date' => 'datetime',
        ];
    }

    /** التبرعات التي يجوز نشرها في الموقع العام (مقبولة فقط). */
    public function scopeVisiblePublicly(Builder $query): Builder
    {
        return $query->where('status', DonationStatus::Accepted->value);
    }

    public function scopeStatus(Builder $query, DonationStatus|string|null $status): Builder
    {
        return $status === null || $status === '' || $status === 'all'
            ? $query
            : $query->where('status', $status instanceof DonationStatus ? $status->value : $status);
    }

    public function scopeType(Builder $query, DonationType|string|null $type): Builder
    {
        return $type === null || $type === '' || $type === 'all'
            ? $query
            : $query->where('type', $type instanceof DonationType ? $type->value : $type);
    }

    /** اسم المتبرع كما يظهر للعامة («متبرع كريم» إن اختار الإخفاء). */
    public function displayDonorName(): string
    {
        return $this->is_anonymous ? 'متبرع كريم' : $this->donor_name;
    }

    /** اسم النوع كما يظهر للجميع: النوع المخصص عند اختيار «غير ذلك». */
    public function typeLabel(): string
    {
        if ($this->type === DonationType::Other && filled($this->custom_type)) {
            return $this->custom_type;
        }

        return $this->type->label();
    }

    /** عنوان المساهمة للعرض: النوع نفسه عند غياب العنوان. */
    public function displayTitle(): string
    {
        return filled($this->title) ? $this->title : $this->typeLabel();
    }

    /** «100 $» أو «50,000 ل.س» للنوع المادي، وبلا قيمة لغيره. */
    public function amountLabel(): ?string
    {
        if ($this->type !== DonationType::Financial || $this->amount === null || $this->currency === null) {
            return null;
        }

        return number_format((float) $this->amount, $this->amount == (int) $this->amount ? 0 : 2)
            .' '.$this->currency->symbol();
    }

    /** ملخص مختصر لإشعارات البوابات: «تبرع مادي بقيمة 100 $ — عنوان». */
    public function notificationSummary(): string
    {
        if ($this->amountLabel() !== null) {
            return 'تبرع مادي بقيمة '.$this->amountLabel().(filled($this->title) ? ' — '.$this->title : '');
        }

        return $this->typeLabel().(filled($this->title) ? ': '.$this->title : '');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
