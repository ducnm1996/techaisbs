<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordLinkRequest;
use App\Http\Requests\SetPasswordRequest;
use App\Models\Device;
use App\Models\User;
use App\Services\Audit;
use App\Services\InvitationMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::guard('web')->attempt($request->safe()->only(['email', 'password']) + ['is_active' => true, 'setup_pending' => false])) {
            throw ValidationException::withMessages(['email' => 'Thông tin đăng nhập chưa đúng.']);
        }
        $request->session()->regenerate();
        $request->session()->forget('mfa_user');

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function sendLink(PasswordLinkRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->validated('email'))->where('is_active', true)->first();
        if ($user) {
            app(InvitationMailer::class)->send($user);
        }

        return back()->with('status', 'Nếu tài khoản hợp lệ, liên kết đã được gửi.');
    }

    public function reset(SetPasswordRequest $request): RedirectResponse
    {
        $status = DB::transaction(function () use ($request): string {
            DB::table('password_reset_tokens')->where('email', $request->validated('email'))->lockForUpdate()->first();

            return Password::reset($request->validated(), function (User $user, string $password): void {
                abort_unless($user->is_active, 403);
                $user->forceFill(['password' => $password, 'setup_pending' => false, 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                foreach (Device::where('user_id', $user->id)->get() as $device) {
                    $device->tokens()->delete();
                }
                Audit::record($user, 'password.reset', 'user', $user->id);
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Liên kết không hợp lệ hoặc hết hạn.']);
        }

        return redirect()->route('login')->with('status', 'Đã thiết lập mật khẩu. Hãy đăng nhập.');
    }
}
