<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(): JsonResponse
    {
        $classrooms = Classroom::query()
            ->with('sections:id,classroom_id,name')
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return response()->json(['classrooms' => $classrooms]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $classroom = Classroom::create($data);

        return response()->json([
            'message' => 'تم إنشاء الصف',
            'data' => $classroom,
        ], 201);
    }

    public function destroy(Classroom $classroom): JsonResponse
    {
        $classroom->delete();

        return response()->json(['message' => 'تم حذف الصف']);
    }

    public function storeSection(Request $request, Classroom $classroom): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $section = $classroom->sections()->create([...$data, 'tenant_id' => $classroom->tenant_id]);

        return response()->json([
            'message' => 'تم إنشاء الشعبة',
            'data' => $section,
        ], 201);
    }

    public function destroySection(Section $section): JsonResponse
    {
        $section->delete();

        return response()->json(['message' => 'تم حذف الشعبة']);
    }
}
