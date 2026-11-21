@if($event->device_metadata)
<section class="panel mt-4"><h2>Thông tin agent thiết bị</h2><dl class="event-details">
@foreach(['manufacturer'=>'Hãng','model'=>'Model','android_release'=>'Phiên bản Android','sdk'=>'Android SDK','security_patch'=>'Bản vá bảo mật','app_version'=>'Phiên bản app','battery_percent'=>'Pin (%)','charging'=>'Đang sạc','power_save'=>'Tiết kiệm pin','ram_total_bytes'=>'RAM tổng (byte)','ram_available_bytes'=>'RAM khả dụng (byte)','storage_total_bytes'=>'Bộ nhớ tổng (byte)','storage_available_bytes'=>'Bộ nhớ khả dụng (byte)','network_type'=>'Loại mạng','network_metered'=>'Mạng tính phí','internet_validated'=>'Internet được Android xác nhận','timezone'=>'Múi giờ','locale'=>'Ngôn ngữ','uptime_seconds'=>'Thời gian từ lúc khởi động (giây)'] as $key=>$label)
@if(array_key_exists($key,$event->device_metadata))<dt>{{ $label }}</dt><dd>{{ is_bool($event->device_metadata[$key]) ? ($event->device_metadata[$key] ? 'Có' : 'Không') : ($event->device_metadata[$key] ?? 'Không có') }}</dd>@endif
@endforeach
</dl><p class="muted small">Trạng thái do thiết bị khai báo tại thời điểm lấy mẫu. Không phải kết nối trực tuyến thời gian thực.</p></section>
@endif
