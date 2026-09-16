<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quran listening audio (تلاوات الاستماع)
    |--------------------------------------------------------------------------
    |
    | External reciter CDN used by «خطة الاستماع». Tracks are addressed per
    | ayah as SSSAAA.mp3 (surah and ayah zero-padded to 3 digits) — the
    | everyayah.com layout. Override the base URL or add reciters as needed.
    |
    */

    'reciter' => env('QURAN_AUDIO_RECITER', 'ar.alafasy'),

    'reciters' => [
        'ar.alafasy' => [
            'label' => 'مشاري راشد العفاسي',
            'base_url' => 'https://everyayah.com/data/Alafasy_128kbps',
        ],
        'ar.abdulbasitmurattal' => [
            'label' => 'عبد الباسط عبد الصمد (مرتل)',
            'base_url' => 'https://everyayah.com/data/Abdul_Basit_Murattal_192kbps',
        ],
        'ar.minshawi' => [
            'label' => 'محمد صديق المنشاوي (مرتل)',
            'base_url' => 'https://everyayah.com/data/Minshawy_Murattal_128kbps',
        ],
        'ar.husary' => [
            'label' => 'محمود خليل الحصري',
            'base_url' => 'https://everyayah.com/data/Husary_128kbps',
        ],
        'ar.mahermuaiqly' => [
            'label' => 'ماهر المعيقلي',
            'base_url' => 'https://everyayah.com/data/MaherAlMuaiqly128kbps',
        ],
    ],

];
