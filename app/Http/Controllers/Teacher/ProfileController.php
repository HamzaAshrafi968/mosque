<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\HandlesProfilePhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends BaseTeacherController
{
    use HandlesProfilePhoto;

    public function edit(Request $request): View
    {
        return view('teacher.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], $this->profilePhotoRules()));

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $photo = $this->resolveProfilePhoto($request, $user->photo);
        $data = array_merge($data, $photo);

        $user->update($data);

        $user->teacher()->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            ...$photo,
        ]);

        return back()->with('success', 'تم تحديث الملف الشخصي');
    }
}
