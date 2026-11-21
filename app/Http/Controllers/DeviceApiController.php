<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventBatchRequest;
use App\Http\Requests\PairRequest;
use App\Models\Device;
use App\Models\HistoryEvent;
use App\Models\PairingCode;
use App\Models\User;
use App\Services\Audit;
use App\Services\PhoneIndex;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeviceApiController extends Controller
{
    public function pair(PairRequest $request): JsonResponse
    {
        $result = DB::transaction(function () use ($request): array {
            $pair = PairingCode::where('code_hash', hash('sha256', strtoupper($request->validated('code'))))->lockForUpdate()->first();
            if (! $pair || $pair->used_at || $pair->expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => 'Mã không hợp lệ hoặc hết hạn.']);
            }
            $owner = User::whereKey($pair->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($owner->is_active && ! $owner->setup_pending, 401);
            $device = Device::create(['user_id' => $owner->id, 'name' => $request->validated('device_name'), 'sources' => $pair->sources, 'allow_ip' => $pair->allow_ip, 'allow_location' => $pair->allow_location]);
            $expiry = now()->addDays(config('history.token_days'));
            $token = $device->createToken('android-sync', ['events:write'], $expiry);
            $pair->update(['used_at' => now()]);
            Audit::record($owner, 'device.paired', 'device', $device->id);

            return ['device_id' => $device->id, 'token' => $token->plainTextToken, 'expires_at' => $expiry->toIso8601String(), 'sources' => $device->sources, 'allow_ip' => $device->allow_ip, 'allow_location' => $device->allow_location];
        }, 3);

        return response()->json($result, 201);
    }

    public function events(EventBatchRequest $request, PhoneIndex $phone): JsonResponse
    {
        $device = $request->user();
        $created = DB::transaction(function () use ($device, $request, $phone): int {
            $owner = User::whereKey($device->user_id)->lockForUpdate()->firstOrFail();
            $locked = Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
            abort_unless($owner->is_active && ! $owner->setup_pending && ! $locked->revoked_at && $locked->tokens()->whereKey($device->currentAccessToken()->id)->exists(), 401);
            $count = 0;
            foreach ($request->validated('events') as $event) {
                abort_unless(in_array($event['source'], $locked->sources, true), 403);
                $values = ['user_id' => $owner->id, 'source' => $event['source'], 'occurred_at' => CarbonImmutable::parse($event['occurred_at'])->utc(), 'phone_index' => isset($event['sender_phone']) ? $phone->hash($event['sender_phone']) : null];
                foreach (['sender_phone', 'sender_name', 'server_domain', 'app_package', 'call_direction', 'duration_seconds', 'device_metadata'] as $field) {
                    $values[$field] = $event[$field] ?? null;
                }
                $values['device_ip'] = $locked->allow_ip ? $request->ip() : null;
                foreach (['latitude', 'longitude', 'accuracy_m'] as $field) {
                    $values[$field] = $locked->allow_location && isset($event[$field]) ? (string) $event[$field] : null;
                }
                $saved = HistoryEvent::query()->createOrFirst(['device_id' => $locked->id, 'event_id' => strtolower($event['event_id'])], $values);
                if ($saved->wasRecentlyCreated) {
                    $count++;
                }
            }

            return $count;
        }, 3);

        return response()->json(['created' => $created, 'duplicates' => count($request->validated('events')) - $created], $created ? 201 : 200);
    }
}
