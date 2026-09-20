<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\AudioUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function rules(): array
    {
        $tenantId = config('app.current_tenant_id') ?: $this->user()?->tenant_id;
        $sessionId = $this->input('study_session_id');

        // الصف يجب أن يخص الدوام المستهدف أو يكون صفًا مشتركًا (بلا دوام).
        $classroomRule = Rule::exists('classrooms', 'id')->where(function ($query) use ($tenantId, $sessionId) {
            $query->where('tenant_id', $tenantId);

            if (filled($sessionId)) {
                $query->where(function ($sub) use ($sessionId) {
                    $sub->whereNull('study_session_id')->orWhere('study_session_id', $sessionId);
                });
            }
        });

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'required_without:audio'],
            'audience' => ['required', 'in:all,teachers,guardians,classroom,classrooms'],
            'study_session_id' => ['nullable', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $tenantId)],
            'classroom_id' => ['nullable', 'required_if:audience,classroom', $classroomRule],
            'audio' => AudioUpload::rules(),
            'auto_delete' => ['nullable', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
