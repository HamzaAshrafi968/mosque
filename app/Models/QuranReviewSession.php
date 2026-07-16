<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuranReviewSession extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'student_id',
        'surah_id',
        'from_ayah',
        'to_ayah',
        'total_words',
        'correct_words',
        'incorrect_words',
        'hesitation_words',
        'tajweed_error_words',
        'added_words',
        'forgotten_words',
        'mastery_percentage',
        'date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'from_ayah' => 'integer',
            'to_ayah' => 'integer',
            'total_words' => 'integer',
            'correct_words' => 'integer',
            'incorrect_words' => 'integer',
            'hesitation_words' => 'integer',
            'tajweed_error_words' => 'integer',
            'added_words' => 'integer',
            'forgotten_words' => 'integer',
            'mastery_percentage' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function surah(): BelongsTo
    {
        return $this->belongsTo(QuranSurah::class, 'surah_id');
    }

    public function words(): HasMany
    {
        return $this->hasMany(QuranReviewWord::class, 'review_session_id');
    }
}
