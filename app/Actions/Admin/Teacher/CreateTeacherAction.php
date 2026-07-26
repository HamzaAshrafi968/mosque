<?php

namespace App\Actions\Admin\Teacher;

use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateTeacherAction
{
    public function execute(TeacherRepositoryInterface $repository, array $data, Request $request): Teacher
    {
        return DB::transaction(function () use ($data, $request, $repository) {
            $userId = null;

            if ($request->filled('password')) {
                $user = User::create([
                    'tenant_id' => $request->user()->tenant_id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $request->input('password'),
                    'role' => 'teacher',
                    'gender' => $data['gender'],
                    'phone' => $data['phone'] ?? null,
                ]);
                $userId = $user->id;
            }

            return $repository->create([...$data, 'user_id' => $userId]);
        });
    }
}
