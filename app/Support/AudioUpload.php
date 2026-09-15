<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared helpers for audio announcements.
 *
 * Files are stored on the public disk under `announcements/audio/` with UUID
 * names (and an extension derived from the detected MIME type) so uploaded
 * filenames can never leak into the web root or collide across tenants.
 */
final class AudioUpload
{
    /** Maximum upload size in kilobytes (20 MB). */
    public const MAX_KILOBYTES = 20480;

    public const DIRECTORY = 'announcements/audio';

    /** Safe extension per detected audio MIME type. */
    private const EXTENSIONS = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/aac' => 'aac',
        'audio/x-aac' => 'aac',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/vnd.wave' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/opus' => 'opus',
        'audio/webm' => 'webm',
        'audio/flac' => 'flac',
        'audio/x-flac' => 'flac',
        'audio/x-ms-wma' => 'wma',
    ];

    /** Validation rules reused by the web and API announcement forms. */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimetypes:audio/*',
            'max:'.self::MAX_KILOBYTES,
        ];
    }

    public static function store(UploadedFile $file): string
    {
        $name = Str::uuid().'.'.self::extensionFor($file);

        return $file->storeAs(self::DIRECTORY, $name, 'public');
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private static function extensionFor(UploadedFile $file): string
    {
        return self::EXTENSIONS[$file->getMimeType()] ?? 'mp3';
    }
}
