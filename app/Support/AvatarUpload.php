<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

/**
 * Shared helpers for uploading / replacing / removing profile photos.
 * Files are stored on the public disk under `avatars/` with UUID names so
 * photos never collide across tenants.
 */
final class AvatarUpload
{
    /** Validation rules reused by every photo-aware form. */
    public static function rules(): array
    {
        return [
            'nullable',
            File::image()
                ->types(['jpg', 'jpeg', 'png', 'webp'])
                ->max(2048),
        ];
    }

    public static function store(UploadedFile $file): string
    {
        $name = Str::uuid().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('avatars', $name, 'public');
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
