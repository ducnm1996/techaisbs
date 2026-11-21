@php
    $directions = ['incoming'=>'Gọi đến','outgoing'=>'Gọi đi','missed'=>'Gọi nhỡ','rejected'=>'Đã từ chối','blocked'=>'Bị chặn','unknown'=>'Không xác định'];
@endphp
<div class="panel table-responsive">
<table class="table align-middle">
<thead><tr><th>Thời gian</th><th>Nguồn</th><th>Liên hệ</th><th>Thiết bị / ứng dụng</th><th>Thông tin bổ sung</th><th>Thao tác</th></tr></thead>
<tbody>
@forelse($entries as $event)
<tr>
    <td class="text-nowrap"><a href="{{ route('history.show',$event) }}">{{ $event->occurred_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}</a><div class="muted small mt-1">Nhận: {{ $event->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m H:i:s') }}</div></td>
    <td><span class="source-badge">{{ $event->source === 'network' ? 'VPN' : ($event->source === 'device' ? 'AGENT' : strtoupper($event->source)) }}</span></td>
    <td>@if($event->source === 'network')<div>Kết nối ứng dụng</div><div class="muted small">Không gắn với liên hệ</div>@else<div>{{ $event->sender_name ?? 'Chưa có tên' }}</div><div class="small mt-1">{{ $event->sender_phone ?? 'Không có số' }}</div>@endif</td>
    <td><div>{{ $event->device?->name ?? 'Thiết bị không còn tồn tại' }}</div><div class="muted small mt-1" style="overflow-wrap:anywhere">{{ $event->app_package ?? 'Không có thông tin ứng dụng' }}</div></td>
    <td>
        @if($event->source === 'call')
            <div>{{ $directions[$event->call_direction] ?? 'Chưa có chiều gọi' }}</div>
            <div class="small">Thời lượng: {{ $event->duration_seconds !== null ? $event->duration_seconds.' giây' : 'Chưa có' }}</div>
        @endif
        @if($event->network_metadata)
            <div><strong>IP đích: {{ $event->network_metadata['destination_ip'] }}</strong></div>
            <div class="small">Cổng {{ $event->network_metadata['destination_port'] }} · {{ strtoupper($event->network_metadata['protocol']) }} · {{ strtoupper($event->network_metadata['app_source']) }}</div>
        @endif
        @if($event->device_ip !== null)<div class="small">IP thiết bị: {{ $event->device_ip }}</div>@endif
        @if($event->latitude !== null && $event->longitude !== null)
            <div class="small">Vị trí: {{ $event->latitude }}, {{ $event->longitude }}</div>
            @if($event->accuracy_m !== null)<div class="small muted">Sai số: {{ $event->accuracy_m }} m</div>@endif
        @endif
        @if($event->server_domain !== null)<div class="small">Máy chủ khai báo: {{ $event->server_domain }}</div>@endif
        @if($event->device_metadata)
            @php($metadata = $event->device_metadata)
            <div class="small">{{ $metadata['manufacturer'] ?? '' }} {{ $metadata['model'] ?? '' }}</div>
            <div class="small">Pin: {{ $metadata['battery_percent'] ?? '—' }}% · Mạng: {{ $metadata['network_type'] ?? '—' }}</div>
        @endif
        @if($event->source !== 'call' && !$event->device_metadata && !$event->network_metadata && $event->device_ip === null && $event->latitude === null && $event->server_domain === null)
            <span class="muted small">Không có metadata bổ sung</span>
        @endif
    </td>
    <td><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary btn-sm" href="{{ route('history.show',$event) }}">Chi tiết</a><form method="post" action="{{ route('history.delete',$event) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Xóa</button></form></div></td>
</tr>
@empty
<tr><td colspan="6" class="empty">Chưa có sự kiện phù hợp với bộ lọc.</td></tr>
@endforelse
</tbody>
</table>
</div>
<p class="muted small mt-3">IP/vị trí thuộc thiết bị đồng bộ và chỉ hiện khi đã được cho phép thu thập. Dữ liệu chưa được gửi sẽ không được bổ sung ngược cho sự kiện cũ. Bấm Chi tiết để xem toàn bộ metadata của từng sự kiện.</p>
