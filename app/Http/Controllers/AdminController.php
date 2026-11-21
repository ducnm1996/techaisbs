<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Http\Requests\AccountUpdateRequest;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\PairingCode;
use App\Models\User;
use App\Services\Audit;
use App\Services\InvitationMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin', ['users' => User::orderBy('id')->paginate(20), 'devices' => Device::latest()->paginate(20, ['*'], 'devices_page'), 'logs' => AuditLog::latest('id')->paginate(30, ['*'], 'audit_page')]);
    }

    public function create(AccountRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->validated() + ['password' => Str::random(64), 'is_active' => true, 'setup_pending' => true]);
            Audit::record($request->user('web'), 'account.created', 'user', $user->id, ['role' => $user->role]);

            return $user;
        });
        $sent = app(InvitationMailer::class)->send($user);

        return back()->with('status', $sent ? 'Đã tạo tài khoản và gửi liên kết thiết lập mật khẩu.' : 'Đã tạo tài khoản nhưng chưa gửi được email. Kiểm tra SMTP rồi gửi lại liên kết.');
    }

    public function update(AccountUpdateRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);
        DB::transaction(function () use ($request, $user): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $changes = ['role_from' => $locked->role, 'role_to' => $data['role'], 'active_from' => $locked->is_active, 'active_to' => (bool) $data['is_active']];
            if ($locked->role !== $data['role']) {
                $data += ['mfa_secret' => null, 'mfa_confirmed_at' => null, 'mfa_last_step' => null];
            }
            $locked->update($data);
            DB::table('sessions')->where('user_id', $locked->id)->delete();
            if (! $locked->is_active) {
                PairingCode::where('user_id', $locked->id)->delete();
                foreach (Device::where('user_id', $locked->id)->get() as $device) {
                    $device->tokens()->delete();
                }
            }
            Audit::record($request->user('web'), 'account.updated', 'user', $locked->id, $changes);
        });

        return back()->with('status', 'Đã cập nhật quyền/trạng thái tài khoản.');
    }

    public function invite(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);
        abort_unless($user->is_active, 403);
        app(InvitationMailer::class)->send($user);
        Audit::record($request->user('web'), 'account.invited', 'user', $user->id);

        return back()->with('status', 'Đã yêu cầu gửi liên kết thiết lập mật khẩu.');
    }
}
