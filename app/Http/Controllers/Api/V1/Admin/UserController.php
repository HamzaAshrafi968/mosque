<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseApiController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->userRepository->paginateUsers();

        return $this->paginated(UserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userRepository->create($request->validated());

        return $this->created(
            UserResource::make($user),
            'تم إنشاء المستخدم'
        );
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user = $this->userRepository->update($id, $data);

        return $this->success(
            UserResource::make($user),
            'تم تحديث المستخدم'
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($id === $request->user()->id) {
            return $this->forbidden('لا يمكن حذف نفسك');
        }

        $this->userRepository->delete($id);

        return $this->success(message: 'تم حذف المستخدم');
    }
}
