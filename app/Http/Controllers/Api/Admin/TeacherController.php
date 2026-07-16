<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teachers = Teacher::query()
            ->withCount('subjects')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->input('gender')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return response()->json(['teachers' => $teachers]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $teacher = DB::transaction(function () use ($data, $request) {
            $userId = null;

            if ($request->filled('password')) {
                $user = User::create([
                    'tenant_id' => $request->user()->tenant_id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $request->input('password'),
                    'role' => 'teacher',
                    'gender' => $data['gender'],
                    'phone' => $data['phone'] ?? null,
                ]);
                $userId = $user->id;
            }

            return Teacher::create([...$data, 'user_id' => $userId]);
        });

        return response()->json([
            'message' => 'تمت إضافة المعلم بنجاح',
            'data' => $teacher,
        ], 201);
    }

    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $teacher->update($this->validated($request));

        return response()->json([
            'message' => 'تم تحديث بيانات المعلم',
            'data' => $teacher->fresh(),
        ]);
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        $teacher->delete();

        return response()->json(['message' => 'تم حذف المعلم']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'hired_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);
    }
}
