<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:200']);
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu chưa đúng.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|max:254|unique:users,email',
            'password' => ['required', 'confirmed', 'max:200', Password::min(12)],
            'pregnancy_start' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
        ]);
        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Chào bạn! Nhật ký của bạn đã sẵn sàng.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
