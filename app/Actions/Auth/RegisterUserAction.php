<?php

namespace App\Actions\Auth;

use App\Services\RegisterService;

class RegisterUserAction
{
    public function execute(RegisterService $service, array $data): array
    {
        $user = $service->register($data);
        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
