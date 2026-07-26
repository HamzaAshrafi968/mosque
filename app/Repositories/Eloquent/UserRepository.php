<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function paginateUsers(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->orderBy('name')->paginate($perPage);
    }

    public function getRecipientsExcept(string $userId): Collection
    {
        return $this->model->where('id', '!=', $userId)->orderBy('name')->get(['id', 'name', 'role']);
    }

    public function findByEmailWithoutScope(string $email): ?User
    {
        return $this->model->withoutGlobalScope('tenant')
            ->where('email', $email)
            ->first();
    }
}
