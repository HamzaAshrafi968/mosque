<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(match (true) {
            Auth::user()->isSuperAdmin() => route('super-admin.dashboard'),
            Auth::user()->isAdmin() => route('admin.dashboard'),
            Auth::user()->isGuardian() => route('guardian.dashboard'),
            Auth::user()->isStudent() => route('student.dashboard'),
            default => route('teacher.dashboard'),
        });
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
