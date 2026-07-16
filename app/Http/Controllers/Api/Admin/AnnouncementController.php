<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $announcements = Announcement::query()
            ->with(['author:id,name', 'classroom:id,name'])
            ->latest('published_at')
            ->paginate(15);

        return response()->json([
            'announcements' => $announcements,
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience' => ['required', 'in:all,teachers,guardians,classroom'],
            'classroom_id' => ['nullable', 'required_if:audience,classroom', 'exists:classrooms,id'],
        ]);

        $announcement = Announcement::create([
            ...$data,
            'user_id' => $request->user()->id,
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم نشر الإعلان',
            'data' => $announcement,
        ], 201);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(['message' => 'تم حذف الإعلان']);
    }
}
