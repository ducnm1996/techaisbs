@if($event->network_metadata)
@php($network = $event->network_metadata)
<section class="panel mt-4"><h2>IP đích quan sát qua VPN cục bộ</h2><dl class="event-details"><dt>Nhóm ứng dụng</dt><dd>{{ strtoupper($network['app_source']) }}</dd><dt>IP đích</dt><dd>{{ $network['destination_ip'] }}</dd><dt>Cổng đích</dt><dd>{{ $network['destination_port'] }}</dd><dt>Giao thức</dt><dd>{{ strtoupper($network['protocol']) }}</dd></dl><p class="muted small">Đây là đích kết nối được quan sát trên thiết bị, có thể là CDN, máy chủ trung gian hoặc kết nối khác của ứng dụng. Không chứng minh IP người nhận hay việc máy chủ đã nhận dữ liệu. Không gắn với một tin nhắn cụ thể, không có payload hoặc nội dung TLS. Một endpoint mỗi ứng dụng được gộp theo phút.</p></section>
@endif
