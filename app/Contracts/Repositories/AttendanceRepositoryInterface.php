<?php

namespace App\Contracts\Repositories;

interface AttendanceRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Upsert teacher attendance rows in the legacy `attendances` table
     * (one row per teacher per date).
     */
    public function upsertTeacher(array $rows): void;
}
