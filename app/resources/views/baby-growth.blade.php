@php
    $pregnancyStart = auth()->user()->pregnancy_start->toImmutable()->startOfDay();
    $pregnancyDays = max(0, (int) $pregnancyStart->diffInDays(today(), false));
    $previewDays = min(294, $pregnancyDays);
@endphp
<section class="baby-growth panel" aria-labelledby="baby-heading" data-baby-growth data-start="{{ $pregnancyStart->toDateString() }}" data-days="{{ $pregnancyDays }}">
    <div class="baby-scene" data-stage="{{ $previewDays < 56 ? 'early' : ($previewDays < 98 ? '0' : ($previewDays < 168 ? '1' : ($previewDays < 238 ? '2' : '3'))) }}" role="img" aria-label="Hình minh họa bé Mùi theo giai đoạn thai kỳ">
        <div class="baby-orbit"></div>
        <div class="baby-float"><div class="baby-sprite"></div><div class="baby-seed"></div></div>
        <span class="baby-scene-caption">Một chút lớn lên, mỗi ngày</span>
    </div>
    <div class="baby-information">
        <p class="eyebrow">BÉ MÙI CỦA MẸ</p>
        <h2 id="baby-heading">{{ intdiv($pregnancyDays, 7) }} tuần {{ $pregnancyDays % 7 }} ngày</h2>
        <p class="baby-date" data-baby-date>Hôm nay · {{ today()->format('d/m/Y') }}</p>
        <p data-baby-description>Bé đang lớn lên cùng những trang nhật ký của mẹ.</p>
        <label for="baby-day" class="form-label">Khám phá hành trình của bé</label>
        <input id="baby-day" class="baby-range" type="range" min="0" max="294" value="{{ $previewDays }}" step="1" aria-describedby="baby-note">
        <div class="baby-range-labels"><span>Tuần 0</span><span>Tuần 20</span><span>Tuần 42</span></div>
        <div class="baby-actions">
            <button class="btn btn-primary btn-sm" type="button" data-baby-play aria-pressed="false">▶ Xem bé lớn lên</button>
            <button class="btn btn-light btn-sm" type="button" data-baby-today>Về hôm nay</button>
            <button class="baby-motion" type="button" data-baby-motion aria-pressed="false">Dừng chuyển động</button>
        </div>
        <p class="baby-note" id="baby-note">Hình 3D minh họa theo giai đoạn, không thể hiện kích thước thực tế. Tuần thai tính từ ngày đầu kỳ kinh cuối trong <a href="{{ route('profile') }}">hồ sơ của mẹ</a>; tự cập nhật mỗi ngày.</p>
        @if($pregnancyDays > 294)
            <p class="baby-note">Mốc trong hồ sơ đã qua 42 tuần. Bạn có thể cập nhật ngày mốc trong hồ sơ; hình minh họa dừng ở tuần 42.</p>
        @endif
        <noscript><p class="baby-note">Bật JavaScript để xem chuyển động theo ngày và chọn tuần minh họa.</p></noscript>
    </div>
</section>
@push('scripts')
<script src="/assets/baby-growth.js?v={{ filemtime(public_path('assets/baby-growth.js')) }}" defer></script>
@endpush
