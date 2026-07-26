<?php

namespace App\Enums;

enum GradeStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
}
