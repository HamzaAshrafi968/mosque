<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\RewardPointRepositoryInterface;
use App\Http\Requests\Api\V1\Teacher\StoreRewardPointRequest;
use App\Http\Resources\Api\V1\RewardPointResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardPointController extends BaseTeacherController
{
    public function index(Request $request, RewardPointRepositoryInterface $rewardPointRepository): JsonResponse
    {
        $points = $rewardPointRepository->paginateWithFilters([
            'awarded_by' => $request->user()->id,
            'student_id' => $request->input('student_id'),
        ], $request->input('per_page', 20));

        return $this->paginated(RewardPointResource::collection($points));
    }

    public function store(StoreRewardPointRequest $request, RewardPointRepositoryInterface $rewardPointRepository): JsonResponse
    {
        $rewardPoint = $rewardPointRepository->create([
            ...$request->validated(),
            'awarded_by' => $request->user()->id,
        ]);

        return $this->created(new RewardPointResource($rewardPoint));
    }

    public function destroy(Request $request, string $id, RewardPointRepositoryInterface $rewardPointRepository): JsonResponse
    {
        $point = $rewardPointRepository->findOrFail($id);

        abort_unless(
            $point->awarded_by === $request->user()->id && $point->quran_review_session_id === null,
            403
        );

        $rewardPointRepository->delete($id);

        return $this->noContent();
    }
}
