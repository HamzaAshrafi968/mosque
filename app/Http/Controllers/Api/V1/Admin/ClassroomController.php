<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreClassroomRequest;
use App\Http\Requests\Api\V1\Admin\StoreSectionRequest;
use App\Http\Resources\Api\V1\ClassroomResource;
use App\Http\Resources\Api\V1\SectionResource;
use Illuminate\Http\JsonResponse;

class ClassroomController extends BaseApiController
{
    public function __construct(
        private readonly ClassroomRepositoryInterface $classroomRepository,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success([
            'classrooms' => ClassroomResource::collection(
                $this->classroomRepository->allWithSectionsAndCounts()
            ),
        ]);
    }

    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $classroom = $this->classroomRepository->create($request->validated());

        return $this->created(
            ClassroomResource::make($classroom),
            'تم إنشاء الصف'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->classroomRepository->delete($id);

        return $this->success(message: 'تم حذف الصف');
    }

    public function storeSection(StoreSectionRequest $request, string $classroomId): JsonResponse
    {
        $section = $this->classroomRepository->createSection(
            $classroomId,
            [...$request->validated(), 'tenant_id' => config('app.current_tenant_id')]
        );

        return $this->created(
            SectionResource::make($section),
            'تم إنشاء الشعبة'
        );
    }

    public function destroySection(string $sectionId): JsonResponse
    {
        $this->classroomRepository->deleteSection($sectionId);

        return $this->success(message: 'تم حذف الشعبة');
    }
}
