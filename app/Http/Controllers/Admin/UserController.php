<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesProfilePhoto;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    use HandlesProfilePhoto;

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,teacher'],
            'gender' => ['required', 'in:male,female'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], $this->profilePhotoRules()));

        $data = array_merge($data, $this->resolveProfilePhoto($request));
        unset($data['remove_photo']);

        User::create($data);

        return back()->with('success', 'تم إنشاء المستخدم');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:admin,teacher'],
        ], $this->profilePhotoRules()));

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data = array_merge($data, $this->resolveProfilePhoto($request, $user->photo));
        unset($data['remove_photo']);

        $user->update($data);

        return back()->with('success', 'تم تحديث المستخدم');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403);

        $user->delete();

        return back()->with('success', 'تم حذف المستخدم');
    }
}
