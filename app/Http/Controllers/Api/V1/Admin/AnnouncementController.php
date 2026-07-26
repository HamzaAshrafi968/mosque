<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\AnnouncementRepositoryInterface;
use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreAnnouncementRequest;
use App\Http\Resources\Api\V1\AnnouncementResource;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends BaseApiController
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcementRepository,
        private readonly ClassroomRepositoryInterface $classroomRepository,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success([
            'announcements' => AnnouncementResource::collection(
                $this->announcementRepository->paginateWithAuthor()
            ),
            'classrooms' => $this->classroomRepository->sortedList(),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->announcementRepository->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'published_at' => now(),
        ]);

        return $this->created(
            AnnouncementResource::make($announcement),
            'تم نشر الإعلان'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->announcementRepository->delete($id);

        return $this->success(message: 'تم حذف الإعلان');
    }
}
