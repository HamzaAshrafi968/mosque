<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\AnnouncementRepositoryInterface;
use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreAnnouncementRequest;
use App\Http\Resources\Api\V1\AnnouncementResource;
use App\Support\AudioUpload;
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
        $data = $request->validated();
        $audio = $request->file('audio');
        unset($data['audio']);

        if ($audio) {
            $data['audio_path'] = AudioUpload::store($audio);
            $data['audio_original_name'] = $audio->getClientOriginalName();
            // Audio announcements are removed automatically after one week
            // unless the client explicitly disabled the auto-delete.
            $data['expires_at'] = $request->boolean('auto_delete', true)
                ? now()->addWeek()
                : null;
        }

        unset($data['auto_delete']);

        $announcement = $this->announcementRepository->create([
            ...$data,
            'user_id' => $request->user()->id,
            'published_at' => now(),
        ]);

        return $this->created(
            AnnouncementResource::make($announcement),
            $audio ? 'تم نشر الإعلان الصوتي' : 'تم نشر الإعلان'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->announcementRepository->delete($id);

        return $this->success(message: 'تم حذف الإعلان');
    }
}
