<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\ScheduleRules;

class GenerateScheduleRequest extends ScheduleRequest
{
    public function rules(): array
    {
        return ScheduleRules::rules($this->user()?->tenant_id, weekly: true);
    }
}
