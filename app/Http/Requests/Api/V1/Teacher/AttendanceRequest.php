<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Enums\AttendanceStatus;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    public function rules(): array
    {
        $allowed = implode(',', collect(AttendanceStatus::cases())->pluck('value')->all());

        return [
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ["in:{$allowed}"],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();

            if (! $user) {
                return;
            }

            $tenantId = $user->tenant_id;
            $keys = array_keys((array) $this->input('statuses', []));

            if ($tenantId === null || $keys === []) {
                return;
            }

            $students = Student::query()
                ->active()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $keys)
                ->get(['id', 'section_id']);

            $valid = $students->pluck('id')->all();
            $invalid = array_diff($keys, $valid);

            if ($invalid !== []) {
                $validator->errors()->add('statuses', 'يحتوي الطلب على طلاب غير مسجلين أو غير نشطين في هذا الجامع');

                return;
            }

            // Section-scope enforcement (spec §17/§21): a teacher may only mark
            // students inside sections they are assigned to (or scheduled in).
            $teacher = Teacher::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->first();

            if (! $teacher) {
                $validator->errors()->add('statuses', 'لا يوجد ملف معلم مرتبط بحسابك');

                return;
            }

            $allowedIds = $teacher->manageableSectionIds();
            $foreign = $students
                ->reject(fn (Student $s) => in_array($s->section_id, $allowedIds, true))
                ->pluck('name');

            if ($foreign->isNotEmpty()) {
                $validator->errors()->add('statuses', 'يمكنك تسجيل الحضور فقط للشعب الموكلة إليك — الطلاب غير المسموحين: '.$foreign->implode('، '));
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}
