<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuranReviewWord extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'review_session_id',
        'ayah_id',
        'word_position',
        'word_text',
        'status',
        'error_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'word_position' => 'integer',
        ];
    }

    public function reviewSession(): BelongsTo
    {
        return $this->belongsTo(QuranReviewSession::class, 'review_session_id');
    }

    public function ayah(): BelongsTo
    {
        return $this->belongsTo(QuranAyah::class, 'ayah_id');
    }

    public function scopeOrderByAyah(Builder $query): Builder
    {
        return $query
            ->orderByRaw('(SELECT quran_ayahs.ayah_number FROM quran_ayahs WHERE quran_ayahs.id = quran_review_words.ayah_id)')
            ->orderBy('word_position');
    }
}
