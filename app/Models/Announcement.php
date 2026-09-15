<?php

namespace App\Models;

use App\Support\AudioUpload;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model
{
    use FlushesTenantCache, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'classroom_id',
        'title',
        'body',
        'audience',
        'published_at',
        'audio_path',
        'audio_original_name',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Announcement $announcement) {
            AudioUpload::delete($announcement->audio_path);
        });
    }

    public function hasAudio(): bool
    {
        return filled($this->audio_path);
    }

    public function audioUrl(): ?string
    {
        return $this->audio_path
            ? Storage::disk('public')->url($this->audio_path)
            : null;
    }

    /** Announcements whose auto-delete deadline has passed. */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /** Announcements without a deadline or whose deadline is still ahead. */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $sub) {
            $sub->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
