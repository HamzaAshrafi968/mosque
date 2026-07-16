<?php

namespace App\Models;

use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuranSurah extends Model
{
    use HasFactory, UuidTrait;

    protected $fillable = [
        'name_arabic',
        'name_english',
        'revelation_type',
        'num_ayahs',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'num_ayahs' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function ayahs(): HasMany
    {
        return $this->hasMany(QuranAyah::class, 'surah_id');
    }

    public function reviewSessions(): HasMany
    {
        return $this->hasMany(QuranReviewSession::class, 'surah_id');
    }
}
