@extends('layout')
@section('title','Thiết bị của tôi')
@section('content')
<div class="page-heading"><div><span class="kicker">KẾT NỐI CÓ KIỂM SOÁT</span><h1>Thiết bị của tôi</h1><p class="muted">Mỗi thiết bị có token riêng. Thu hồi sẽ chặn đồng bộ ngay.</p></div></div>
@if(session('pair_code'))<div class="pair-code" role="status"><span>MÃ GHÉP · HẾT HẠN SAU 5 PHÚT</span><strong>{{ session('pair_code') }}</strong><small>Chỉ sử dụng một lần. Không chia sẻ mã này.</small></div>@endif
<form class="panel" method="post" action="{{ route('devices.pair') }}">@csrf<h2>Ghép một thiết bị mới</h2><p>Chọn nguồn bạn cho phép gửi. Android vẫn cần được chủ thiết bị cấp quyền tương ứng.</p><fieldset><legend class="fs-6">Nguồn metadata</legend><div class="source-options">@foreach(config('history.sources') as $source)<label class="check"><input type="checkbox" name="sources[]" value="{{ $source }}"> {{ $source === 'device' ? 'THÔNG TIN THIẾT BỊ / AGENT' : strtoupper($source) }}</label>@endforeach</div></fieldset><div class="source-options mt-3"><label class="check"><input type="checkbox" name="allow_ip" value="1"> Lưu IP kết nối của thiết bị</label><label class="check"><input type="checkbox" name="allow_location" value="1"> Cho phép vị trí thiết bị</label></div><p class="muted small mt-3">Không thu nội dung. Không cung cấp IP/vị trí người nhắn. Tạo mã mới làm mã chưa dùng trước đó hết hiệu lực.</p><button class="btn btn-primary">Tạo mã dùng một lần</button></form>
<div class="table-responsive panel mt-4"><table class="table align-middle"><thead><tr><th>Thiết bị</th><th>Nguồn được phép</th><th>Quyền bổ sung</th><th>Trạng thái</th><th></th></tr></thead><tbody>@forelse($devices as $device)<tr><td>{{ $device->name }}</td><td>{{ implode(', ',$device->sources) }}</td><td>IP: {{ $device->allow_ip?'Bật':'Tắt' }} · Vị trí: {{ $device->allow_location?'Bật':'Tắt' }}</td><td>{{ $device->revoked_at?'Đã thu hồi':'Đã liên kết' }}</td><td>@unless($device->revoked_at)<form method="post" action="{{ route('devices.revoke',$device) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Thu hồi</button></form>@endunless</td></tr>@empty<tr><td colspan="5" class="empty">Bạn chưa ghép thiết bị nào.</td></tr>@endforelse</tbody></table></div>
<section class="mt-4"><h2>Trạng thái agent của tôi</h2><p class="muted">Cập nhật theo lịch và điều kiện mạng/pin Android; không phải trạng thái online thời gian thực.</p>
@foreach($devices as $device)
@if($device->latestTelemetry)
@php($sample = $device->latestTelemetry)
@php($metadata = $sample->device_metadata ?? [])
<article class="panel mb-3"><h3 class="h5">{{ $device->name }}</h3><p>{{ $device->revoked_at ? 'Đã thu hồi' : ($sample->occurred_at->gt(now()->subMinutes(30)) ? 'Có cập nhật gần đây' : 'Chưa có mẫu mới trong 30 phút') }}</p><p>{{ $metadata['manufacturer'] ?? '—' }} {{ $metadata['model'] ?? '—' }} · Android {{ $metadata['android_release'] ?? '—' }} · Pin {{ $metadata['battery_percent'] ?? '—' }}% · Mạng {{ $metadata['network_type'] ?? '—' }}</p><p class="muted small">Lấy mẫu: {{ $sample->occurred_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }} · Nhận: {{ $sample->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}</p><a href="{{ route('history.show',$sample) }}">Xem thông tin thiết bị và vị trí được chia sẻ</a></article>
@endif
@endforeach
</section>
@endsection
