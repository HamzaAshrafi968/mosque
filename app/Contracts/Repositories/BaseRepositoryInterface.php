<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    public function find(string $id): ?Model;

    public function findOrFail(string $id): Model;

    public function all(): Collection;

    public function paginate(int $perPage = 20, array $with = []): LengthAwarePaginator;

    public function create(array $data): Model;

    public function update(string $id, array $data): Model;

    public function delete(string $id): void;
}
