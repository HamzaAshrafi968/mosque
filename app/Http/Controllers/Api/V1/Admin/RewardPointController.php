<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\RewardPointRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\RewardPointResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardPointController extends BaseApiController
{
    public function __construct(
        private readonly RewardPointRepositoryInterface $rewardPointRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $points = $this->rewardPointRepository->paginateWithFilters([
            'student_id' => $request->input('student_id'),
            'type' => $request->input('type'),
        ], $request->input('per_page', 20));

        return $this->paginated(RewardPointResource::collection($points));
    }
}
