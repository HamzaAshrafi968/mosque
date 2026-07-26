<?php

namespace App\Enums;

enum LessonType: string
{
    case File = 'file';
    case Video = 'video';
    case Link = 'link';
    case Presentation = 'presentation';
}
