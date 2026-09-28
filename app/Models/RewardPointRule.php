<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قاعدة منح نقاط تلقائية لدوام معيّن (لكل دوام نقاطه الخاصة).
 * points فارغة/صفر = القاعدة معطّلة لهذا الدوام.
 */
class RewardPointRule extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    public const TYPE_TASMEE_PAGES = 'tasmee_pages';

    public const TYPE_KHAMSA_REVIEW = 'khamsa_review';

    public const TYPE_RETAKE_KHAMSA_REVIEW = 'retake_khamsa_review';

    public const TYPE_TEST_PASS = 'test_pass';

    public const TYPE_LISTENING_PLAN = 'listening_plan_complete';

    public const TYPE_SHARIA_MEMORIZATION = 'sharia_memorization_complete';

    public const TYPES = [
        self::TYPE_TASMEE_PAGES,
        self::TYPE_KHAMSA_REVIEW,
        self::TYPE_RETAKE_KHAMSA_REVIEW,
        self::TYPE_TEST_PASS,
        self::TYPE_LISTENING_PLAN,
        self::TYPE_SHARIA_MEMORIZATION,
    ];

    /**
     * وصف كل نوع قاعدة للواجهة: العنوان، الشرح، وهل له عدد صفحات.
     *
     * @return array<string, array{title: string, description: string, has_pages: bool, placeholder: string, pages_placeholder: ?string}>
     */
    public static function definitions(): array
    {
        return [
            self::TYPE_TASMEE_PAGES => [
                'title' => 'حفظ صفحات جديدة',
                'description' => 'تراكمي مع ترحيل الباقي: من حفظ ٣ صفحات ثم ٢ لاحقاً تكتمل الخمسة وتُمنح نقاطها.',
                'has_pages' => true,
                'placeholder' => '10',
                'pages_placeholder' => '5',
            ],
            self::TYPE_KHAMSA_REVIEW => [
                'title' => 'إتمام خمسة مراجعة (ما بعد الحفظ)',
                'description' => 'تُمنح عند إنهاء خمسة من مراجعة ما بعد الحفظ من شاشة «مراجعة 5» أو تلقائياً عند اجتياز اختبار الدفعة.',
                'has_pages' => false,
                'placeholder' => '2',
                'pages_placeholder' => null,
            ],
            self::TYPE_RETAKE_KHAMSA_REVIEW => [
                'title' => 'إتمام خمسة إعادة رسوب الاختبار',
                'description' => 'تُمنح عند إنهاء خمسة من مراجعة «خمسات إعادة رسوب الاختبار» يدوياً أو تلقائياً عند اجتياز اختبار الإعادة.',
                'has_pages' => false,
                'placeholder' => '5',
                'pages_placeholder' => null,
            ],
            self::TYPE_TEST_PASS => [
                'title' => 'اجتياز اختبار الدفعة',
                'description' => 'تُمنح مرة واحدة لكل اختبار ناجح فقط، ولا شيء عند الرسوب.',
                'has_pages' => false,
                'placeholder' => '20',
                'pages_placeholder' => null,
            ],
            self::TYPE_LISTENING_PLAN => [
                'title' => 'إتمام خطة الاستماع والاختبار',
                'description' => 'تُمنح مرة واحدة عند نجاح الطالب في جميع أجزاء خطة استماع (خطط الدفعات تُفتح تلقائياً).',
                'has_pages' => false,
                'placeholder' => '15',
                'pages_placeholder' => null,
            ],
            self::TYPE_SHARIA_MEMORIZATION => [
                'title' => 'حفظ الدورة الشرعية كاملاً',
                'description' => 'تُمنح مرة واحدة عند تحويل حالة حفظ الطالب في الدورة الشرعية إلى «حفظ كامل».',
                'has_pages' => false,
                'placeholder' => '25',
                'pages_placeholder' => null,
            ],
        ];
    }

    protected $fillable = [
        'tenant_id',
        'study_session_id',
        'rule_type',
        'pages_count',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'pages_count' => 'integer',
            'points' => 'integer',
        ];
    }

    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
    }

    public function isActive(): bool
    {
        return $this->points !== null && $this->points > 0;
    }
}
