<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case All = 'all';
    case Teachers = 'teachers';
    case Guardians = 'guardians';
    case Classroom = 'classroom';
}
