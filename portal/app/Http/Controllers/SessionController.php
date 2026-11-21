<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\MfaRequest;
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
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class SessionController extends Controller
{
    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::guard('web')->attempt($request->safe()->only(['email', 'password']) + ['is_active' => true, 'setup_pending' => false])) {
            throw ValidationException::withMessages(['email' => 'Thông tin đăng nhập chưa đúng.']);
        }
        $request->session()->regenerate();
        $request->session()->forget('mfa_user');

        return redirect()->route($request->user('web')->role === 'admin' ? 'mfa.show' : 'home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function mfa(Request $request, Google2FA $totp): View
    {
        $user = $request->user('web');
        abort_unless($user->role === 'admin', 403);
        DB::transaction(function () use ($user, $totp): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $locked->mfa_secret) {
                $locked->update(['mfa_secret' => $totp->generateSecretKey(32)]);
            }
        });
        $user->refresh();

        return view('mfa', ['secret' => $user->mfa_confirmed_at ? null : $user->mfa_secret]);
    }

    public function confirmMfa(MfaRequest $request, Google2FA $totp): RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user->role === 'admin', 403);
        DB::transaction(function () use ($user, $request, $totp): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $step = $locked->mfa_secret ? $totp->verifyKeyNewer($locked->mfa_secret, $request->validated('code'), $locked->mfa_last_step, 1) : false;
            if ($step === false) {
                throw ValidationException::withMessages(['code' => 'Mã không đúng, đã dùng hoặc đã hết hạn.']);
            }
            $enrolling = ! $locked->mfa_confirmed_at;
            $locked->update(['mfa_confirmed_at' => $locked->mfa_confirmed_at ?? now(), 'mfa_last_step' => $step === true ? $totp->getTimestamp() : $step]);
            if ($enrolling) {
                Audit::record($locked, 'mfa.enrolled', 'user', $locked->id);
            }
        });
        $user->refresh();
        $request->session()->regenerate();
        $request->session()->put('mfa_user', $user->id);

        return redirect()->route('home');
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
