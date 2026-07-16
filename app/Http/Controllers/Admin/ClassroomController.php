<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(): View
    {
        $classrooms = Classroom::query()
            ->with('sections:id,classroom_id,name')
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return view('admin.classrooms.index', ['classrooms' => $classrooms]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        Classroom::create($data);

        return back()->with('success', 'تم إنشاء الصف');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $classroom->delete();

        return back()->with('success', 'تم حذف الصف');
    }

    public function storeSection(Request $request, Classroom $classroom): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $classroom->sections()->create([...$data, 'tenant_id' => $classroom->tenant_id]);

        return back()->with('success', 'تم إنشاء الشعبة');
    }

    public function destroySection(Section $section): RedirectResponse
    {
        $section->delete();

        return back()->with('success', 'تم حذف الشعبة');
    }
}
