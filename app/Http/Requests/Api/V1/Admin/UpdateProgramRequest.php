<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Program;
use App\Support\ProgramRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramRequest extends FormRequest
{
    public function rules(): array
    {
        $program = $this->route('program');

        return ProgramRules::rules(
            config('app.current_tenant_id') ?? $this->user()->tenant_id,
            $program instanceof Program ? $program : null
        );
    }

    public function authorize(): bool
    {
        return true;
    }
}
