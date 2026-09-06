<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

/**
 * Person photo support for profile models (users/students/teachers/parents).
 * `photo` stores the relative path on the public disk; `avatarUrl()` turns it
 * into a fully qualified URL, views fall back to initial-letter avatars when
 * null.
 */
trait HasAvatar
{
    public function avatarUrl(): ?string
    {
        return $this->photo
            ? Storage::disk('public')->url($this->photo)
            : null;
    }

    /** First letter (or first word) used as the fallback avatar label. */
    public function avatarInitial(): string
    {
        $name = trim((string) ($this->name ?? ''));

        return mb_strlen($name) > 0 ? mb_substr($name, 0, 1) : '؟';
    }

    /** Clean up the stored file when the record is deleted. */
    protected static function bootHasAvatar(): void
    {
        static::deleting(function ($model) {
            if ($model->photo) {
                Storage::disk('public')->delete($model->photo);
            }
        });
    }
}
