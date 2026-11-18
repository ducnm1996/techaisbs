@php
    $pregnancyStart = auth()->user()->pregnancy_start->toImmutable()->startOfDay();
    $pregnancyDays = max(0, (int) $pregnancyStart->diffInDays(today(), false));
    $birth = !empty($babyGrowth['birth_date']) ? \Carbon\CarbonImmutable::parse($babyGrowth['birth_date'])->startOfDay() : $pregnancyStart->addDays(280);
    $birthOffset = (int) $pregnancyStart->diffInDays($birth, false);
    $maxDays = (int) $pregnancyStart->diffInDays($birth->addYearsNoOverflow(18), false);
    $previewDays = min($maxDays, $pregnancyDays);
    $isChild = today()->greaterThanOrEqualTo($birth);
@endphp
<section class="baby-growth panel" aria-labelledby="baby-heading" data-baby-growth data-start="{{ $pregnancyStart->toDateString() }}" data-birth="{{ $birth->toDateString() }}" data-estimated="{{ empty($babyGrowth['birth_date']) ? 'true' : 'false' }}" data-gender="{{ $babyGrowth['baby_gender'] ?? 'boy' }}" data-days="{{ $pregnancyDays }}">
    <div class="baby-scene" data-stage="{{ $isChild ? 'child' : ($previewDays < 56 ? 'early' : ($previewDays < 98 ? '0' : ($previewDays < 168 ? '1' : ($previewDays < 238 ? '2' : '3')))) }}" role="img" aria-label="Hình minh họa hành trình lớn lên của bé Mùi">
        <div class="baby-orbit"></div>
        <div class="baby-float"><div class="baby-sprite"></div><div class="child-sprite"></div><div class="baby-seed"></div></div>
        <span class="baby-scene-caption">Từ vòng tay mẹ đến tuổi 18</span>
    </div>
    <div class="baby-information">
        <p class="eyebrow">BÉ MÙI CỦA MẸ</p>
        <h2 id="baby-heading">{{ $isChild ? 'Hành trình lớn lên' : intdiv($pregnancyDays, 7).' tuần '.($pregnancyDays % 7).' ngày' }}</h2>
        <p class="baby-date" data-baby-date>Hôm nay · {{ today()->format('d/m/Y') }}</p>
        <p data-baby-description>Bé đang lớn lên cùng những trang nhật ký của mẹ.</p>
        <form class="baby-settings" action="{{ route('baby.growth') }}" method="post">
            @csrf @method('PUT')
            <div class="baby-settings-fields">
                <div><label class="form-label" for="baby-gender">Hình minh họa</label>
                    <select id="baby-gender" name="baby_gender" class="form-select form-select-sm"><option value="boy" @selected(old('baby_gender', $babyGrowth['baby_gender'] ?? 'boy') === 'boy')>Bé trai</option><option value="girl" @selected(old('baby_gender', $babyGrowth['baby_gender'] ?? 'boy') === 'girl')>Bé gái</option></select>
                </div>
                <div><label class="form-label" for="baby-birth">Ngày sinh (nếu đã sinh)</label>
                    <input type="date" class="form-control form-control-sm" id="baby-birth" name="birth_date" min="{{ $pregnancyStart->addDay()->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ old('birth_date', $babyGrowth['birth_date'] ?? '') }}">
                </div>
                <button type="submit" class="btn btn-light btn-sm">Lưu</button>
            </div>
            <p class="baby-note">Lưu để giữ lựa chọn trên các thiết bị. Chưa nhập ngày sinh: dùng mốc dự kiến 40 tuần.</p>
        </form>
        <label for="baby-day" class="form-label">Khám phá hành trình đến 18 tuổi</label>
        <input id="baby-day" class="baby-range" type="range" min="0" max="{{ $maxDays }}" value="{{ $previewDays }}" step="1" aria-describedby="baby-note">
        <div class="baby-range-labels"><span>Thai kỳ</span><span>Tuổi thơ</span><span>18 tuổi</span></div>
        <div class="baby-milestones" aria-label="Chọn mốc tuổi">
            <button type="button" data-baby-offset="140">20 tuần</button>
            @foreach([0, 1, 3, 6, 12, 18] as $year)
                <button type="button" data-baby-offset="{{ (int) $pregnancyStart->diffInDays($birth->addYearsNoOverflow($year), false) }}">{{ $year === 0 ? 'Sơ sinh' : $year.' tuổi' }}</button>
            @endforeach
        </div>
        <div class="baby-actions">
            <button class="btn btn-primary btn-sm" type="button" data-baby-play aria-pressed="false">▶ Xem bé lớn lên</button>
            <button class="btn btn-light btn-sm" type="button" data-baby-today>Về hôm nay</button>
            <button class="baby-motion" type="button" data-baby-motion aria-pressed="false">Dừng chuyển động</button>
        </div>
        <p class="baby-note" id="baby-note">Hình phong cách 3D có chuyển động, minh họa theo giai đoạn; không dự đoán ngoại hình hay kích thước thực tế. Tuần thai theo ngày mốc trong <a href="{{ route('profile') }}">hồ sơ của mẹ</a>. Tuổi sau sinh theo ngày sinh đã lưu, hoặc ngày sinh dự kiến khi chưa nhập. Hình dừng ở tuổi 18.</p>
        <noscript><p class="baby-note">Bật JavaScript để chọn tuổi và xem chuyển động.</p></noscript>
    </div>
</section>
@push('scripts')
<script src="/assets/baby-growth.js?v={{ filemtime(public_path('assets/baby-growth.js')) }}" defer></script>
@endpush
