<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EventBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*' => ['required', 'array:event_id,source,occurred_at,sender_phone,sender_name,server_domain,latitude,longitude,accuracy_m,app_package,call_direction,duration_seconds,device_metadata'],
            'events.*.event_id' => ['required', 'uuid', 'distinct'],
            'events.*.source' => ['required', Rule::in(config('history.sources'))],
            'events.*.app_package' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)+$/'],
            'events.*.call_direction' => ['nullable', Rule::in(['incoming', 'outgoing', 'missed', 'rejected', 'blocked', 'unknown'])],
            'events.*.duration_seconds' => ['nullable', 'integer', 'between:0,604800'],
            'events.*.device_metadata' => ['sometimes', 'required', 'array:manufacturer,model,android_release,sdk,security_patch,app_version,battery_percent,charging,power_save,ram_total_bytes,ram_available_bytes,storage_total_bytes,storage_available_bytes,network_type,network_metered,internet_validated,timezone,locale,uptime_seconds'],
            'events.*.device_metadata.manufacturer' => ['sometimes', 'string', 'max:80'],
            'events.*.device_metadata.model' => ['sometimes', 'string', 'max:120'],
            'events.*.device_metadata.android_release' => ['sometimes', 'string', 'max:40'],
            'events.*.device_metadata.sdk' => ['sometimes', 'integer', 'between:26,100'],
            'events.*.device_metadata.security_patch' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'events.*.device_metadata.app_version' => ['sometimes', 'string', 'max:24'],
            'events.*.device_metadata.battery_percent' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'events.*.device_metadata.charging' => ['sometimes', 'boolean'],
            'events.*.device_metadata.power_save' => ['sometimes', 'boolean'],
            'events.*.device_metadata.ram_total_bytes' => ['sometimes', 'integer', 'between:0,1099511627776'],
            'events.*.device_metadata.ram_available_bytes' => ['sometimes', 'integer', 'between:0,1099511627776'],
            'events.*.device_metadata.storage_total_bytes' => ['sometimes', 'integer', 'between:0,1125899906842624'],
            'events.*.device_metadata.storage_available_bytes' => ['sometimes', 'integer', 'between:0,1125899906842624'],
            'events.*.device_metadata.network_type' => ['sometimes', Rule::in(['none', 'wifi', 'cellular', 'ethernet', 'vpn', 'other'])],
            'events.*.device_metadata.network_metered' => ['sometimes', 'boolean'],
            'events.*.device_metadata.internet_validated' => ['sometimes', 'boolean'],
            'events.*.device_metadata.timezone' => ['sometimes', 'string', 'max:80'],
            'events.*.device_metadata.locale' => ['sometimes', 'string', 'max:35', 'regex:/^[A-Za-z0-9-]+$/'],
            'events.*.device_metadata.uptime_seconds' => ['sometimes', 'integer', 'between:0,9007199254740991'],
            'events.*.occurred_at' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/'],
            'events.*.sender_phone' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'events.*.sender_name' => ['nullable', 'string', 'max:120'],
            'events.*.server_domain' => ['nullable', 'string', 'max:253', 'regex:/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/'],
            'events.*.latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:events.*.longitude'],
            'events.*.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:events.*.latitude'],
            'events.*.accuracy_m' => ['nullable', 'numeric', 'between:0,100000', 'required_with:events.*.latitude'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->input('events') as $index => $event) {
                if (isset($event['device_metadata']) && $event['source'] !== 'device') {
                    $validator->errors()->add("events.$index.device_metadata", 'Thông tin thiết bị chỉ được dùng cho nguồn device.');
                }
                if ($event['source'] === 'device' && empty($event['device_metadata'])) {
                    $validator->errors()->add("events.$index.device_metadata", 'Cần có thông tin thiết bị đã được cho phép.');
                }
                if ($event['source'] !== 'call' && (isset($event['call_direction']) || isset($event['duration_seconds']))) {
                    $validator->errors()->add("events.$index.call_direction", 'Metadata cuộc gọi chỉ được dùng cho nguồn call.');
                }
                if (! in_array($event['source'], $this->user()->sources, true)) {
                    $validator->errors()->add("events.$index.source", 'Nguồn chưa được chủ thiết bị cho phép.');
                }
                if (! $this->user()->allow_location && (isset($event['latitude']) || isset($event['longitude']) || isset($event['accuracy_m']))) {
                    $validator->errors()->add("events.$index.latitude", 'Chưa được phép gửi vị trí.');
                }
                $time = CarbonImmutable::parse($event['occurred_at']);
                if ($time->isBefore(now()->subDays(config('history.retention_days'))) || $time->isAfter(now()->addMinutes(5))) {
                    $validator->errors()->add("events.$index.occurred_at", 'Thời gian nằm ngoài khoảng cho phép.');
                }
            }
            if (array_diff(array_keys($this->all()), ['events'])) {
                $validator->errors()->add('events', 'Không chấp nhận trường ngoài hợp đồng API.');
            }
        });
    }
}
