<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::query()
            ->with(['author:id,name', 'classroom:id,name'])
            ->latest('published_at')
            ->paginate(15);

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience' => ['required', 'in:all,teachers,guardians,classroom'],
            'classroom_id' => ['nullable', 'required_if:audience,classroom', 'exists:classrooms,id'],
        ]);

        Announcement::create([
            ...$data,
            'user_id' => $request->user()->id,
            'published_at' => now(),
        ]);

        return back()->with('success', 'تم نشر الإعلان');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'تم حذف الإعلان');
    }
}
