<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $teachers = Teacher::query()
            ->withCount('subjects')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->input('gender')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.teachers.index', ['teachers' => $teachers]);
    }

    public function create(): View
    {
        return view('admin.teachers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
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

            Teacher::create([...$data, 'user_id' => $userId]);
        });

        return redirect()->route('admin.teachers.index')->with('success', 'تمت إضافة المعلم بنجاح');
    }

    public function edit(Teacher $teacher): View
    {
        return view('admin.teachers.edit', ['teacher' => $teacher]);
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $teacher->update($this->validated($request, $teacher));

        return redirect()->route('admin.teachers.index')->with('success', 'تم تحديث بيانات المعلم');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $teacher->delete();

        return redirect()->route('admin.teachers.index')->with('success', 'تم حذف المعلم');
    }

    private function validated(Request $request, ?Teacher $teacher = null): array
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
