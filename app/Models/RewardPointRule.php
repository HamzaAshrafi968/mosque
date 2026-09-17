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

    public const TYPE_TEST_PASS = 'test_pass';

    public const TYPES = [
        self::TYPE_TASMEE_PAGES,
        self::TYPE_KHAMSA_REVIEW,
        self::TYPE_TEST_PASS,
    ];

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
