<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(): View
    {
        return view('admin.subjects.index', [
            'subjects' => Subject::with('teacher:id,name')->orderBy('name')->get(),
            'teachers' => Teacher::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Subject::create($this->validated($request));

        return back()->with('success', 'تمت إضافة المادة');
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $subject->update($this->validated($request));

        return back()->with('success', 'تم تحديث المادة');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return back()->with('success', 'تم حذف المادة');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'weekly_lessons' => ['required', 'integer', 'min:1', 'max:50'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
        ]);
    }
}
