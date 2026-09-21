<?php

namespace App\Support;

/**
 * قواعد رفع المرفقات التعليمية (واجبات/دروس).
 *
 * قائمة MIME وامتدادات موثوقة فقط — بلا HTML/SVG/JS/سكربتات — لمنع
 * XSS المخزَّن واستضافة ملفات خطرة على نطاق التطبيق.
 */
final class DocumentUpload
{
    /** أنواع MIME المسموحة للمرفقات التعليمية. */
    public const MIMETYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'application/x-rar-compressed',
        'application/vnd.rar',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'text/plain',
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
        'video/mp4',
    ];

    /** الامتدادات المسموحة (حماية إضافية بجانب فحص MIME). */
    public const EXTENSIONS = 'pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,jpeg,jpg,png,webp,gif,txt,mp3,wav,ogg,mp4';

    /**
     * قواعد التحقق لملف واحد. استخدمها مع 'nullable' أو 'required_if'.
     *
     * @return array<int, string>
     */
    public static function rules(int $maxKb): array
    {
        return [
            'file',
            'max:'.$maxKb,
            'extensions:'.self::EXTENSIONS,
            'mimetypes:'.implode(',', self::MIMETYPES),
        ];
    }
}
