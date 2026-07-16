<?php

namespace App\Models;

use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuranAyah extends Model
{
    use HasFactory, UuidTrait;

    protected $fillable = [
        'surah_id',
        'ayah_number',
        'text',
        'text_simple',
    ];

    protected function casts(): array
    {
        return [
            'ayah_number' => 'integer',
        ];
    }

    public function surah(): BelongsTo
    {
        return $this->belongsTo(QuranSurah::class, 'surah_id');
    }

    public function reviewWords(): HasMany
    {
        return $this->hasMany(QuranReviewWord::class, 'ayah_id');
    }
}
