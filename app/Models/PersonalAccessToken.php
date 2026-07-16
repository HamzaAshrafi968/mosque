<?php

namespace App\Models;

use App\Traits\UuidTrait;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use UuidTrait;
}
