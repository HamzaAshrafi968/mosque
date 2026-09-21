<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    public function execute(array $data): array
    {
        $user = User::withoutGlobalScope('tenant')
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'بيانات الدخول غير صحيحة',
            ]);
        }

        // جامع موقوف: لا تُصدر له توكنات API.
        if ($user->tenant?->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => 'حساب الجامع غير مُفعّل حالياً',
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
