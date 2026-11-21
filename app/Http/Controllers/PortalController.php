<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceOptionsRequest;
use App\Http\Requests\HistoryFilterRequest;
use App\Http\Requests\PhoneSearchRequest;
use App\Models\Device;
use App\Models\HistoryEvent;
use App\Models\PairingCode;
use App\Models\User;
use App\Services\Audit;
use App\Services\PhoneIndex;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function home(Request $request): View
    {
        $id = $request->user('web')->id;

        return view('home', ['eventCount' => HistoryEvent::where('user_id', $id)->count(), 'deviceCount' => Device::where('user_id', $id)->whereNull('revoked_at')->count()]);
    }

    public function history(HistoryFilterRequest $request): View
    {
        Gate::authorize('viewAny', HistoryEvent::class);
        $query = HistoryEvent::where('user_id', $request->user('web')->id);
        foreach (['source' => 'source', 'device' => 'device_id'] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }
        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse($request->validated('from'), 'Asia/Ho_Chi_Minh')->utc());
        }
        if ($request->filled('to')) {
            $query->where('occurred_at', '<', CarbonImmutable::parse($request->validated('to'), 'Asia/Ho_Chi_Minh')->addDay()->utc());
        }
        if ($request->session()->has('phone_search')) {
            $query->where('phone_index', $request->session()->get('phone_search'));
        }

        return view('history', ['entries' => $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(25)->withQueryString(), 'devices' => Device::where('user_id', $request->user('web')->id)->get()]);
    }

    public function search(PhoneSearchRequest $request, PhoneIndex $phone): RedirectResponse
    {
        $request->session()->forget('phone_search');
        if ($request->filled('phone')) {
            $request->session()->put('phone_search', $phone->hash($request->validated('phone')));
        }

        return redirect()->route('history');
    }

    public function show(HistoryEvent $event): View
    {
        Gate::authorize('view', $event);

        return view('event', compact('event'));
    }

    public function delete(HistoryEvent $event): RedirectResponse
    {
        Gate::authorize('delete', $event);
        $event->delete();

        return redirect()->route('history')->with('status', 'Đã xóa sự kiện.');
    }

    public function devices(Request $request): View
    {
        return view('devices', ['devices' => Device::where('user_id', $request->user('web')->id)->latest()->get()]);
    }

    public function createPair(DeviceOptionsRequest $request): RedirectResponse
    {
        Gate::authorize('create', Device::class);
        $code = strtoupper(bin2hex(random_bytes(8)));
        DB::transaction(function () use ($request, $code): void {
            User::whereKey($request->user('web')->id)->lockForUpdate()->firstOrFail();
            PairingCode::where('user_id', $request->user('web')->id)->whereNull('used_at')->delete();
            PairingCode::create(['user_id' => $request->user('web')->id, 'code_hash' => hash('sha256', $code), 'sources' => $request->validated('sources'), 'allow_ip' => $request->boolean('allow_ip'), 'allow_location' => $request->boolean('allow_location'), 'expires_at' => now()->addMinutes(5)]);
        });

        return redirect()->route('devices')->with('pair_code', $code);
    }

    public function revoke(Request $request, Device $device): RedirectResponse
    {
        Gate::authorize('revoke', $device);
        DB::transaction(function () use ($request, $device): void {
            User::whereKey($device->user_id)->lockForUpdate()->firstOrFail();
            $locked = Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $locked->update(['revoked_at' => now()]);
            $locked->tokens()->delete();
            Audit::record($request->user('web'), 'device.revoked', 'device', $locked->id);
        });

        return back()->with('status', 'Đã thu hồi thiết bị.');
    }
}
