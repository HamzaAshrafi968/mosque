<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\ProgramRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreProgramRequest extends FormRequest
{
    public function rules(): array
    {
        return ProgramRules::rules(config('app.current_tenant_id') ?? $this->user()->tenant_id);
    }

    public function authorize(): bool
    {
        return true;
    }
}
