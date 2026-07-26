<?php

namespace App\Enums;

enum RewardPointType: string
{
    case Earned = 'earned';
    case Deducted = 'deducted';
}
