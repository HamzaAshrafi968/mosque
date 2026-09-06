<?php

namespace App\Http\Controllers\Concerns;

use App\Support\AvatarUpload;
use Illuminate\Http\Request;

/**
 * Shared request handling for profile photos: `photo` (file) replaces the
 * current avatar, `remove_photo` clears it. Resolution is safe for both
 * create (no current photo) and update flows.
 */
trait HandlesProfilePhoto
{
    /** Merge with the form's own validation array. */
    protected function profilePhotoRules(): array
    {
        return [
            'photo' => AvatarUpload::rules(),
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string|null>  empty when untouched,
     *                                      ['photo' => path|null] when changed.
     */
    protected function resolveProfilePhoto(Request $request, ?string $currentPhoto = null): array
    {
        if ($request->hasFile('photo')) {
            if ($currentPhoto) {
                AvatarUpload::delete($currentPhoto);
            }

            return ['photo' => AvatarUpload::store($request->file('photo'))];
        }

        if ($request->boolean('remove_photo')) {
            AvatarUpload::delete($currentPhoto);

            return ['photo' => null];
        }

        return [];
    }
}
