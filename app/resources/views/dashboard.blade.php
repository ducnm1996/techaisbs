@extends('layout')
@section('content')
@php
    $days = max(0, (int) auth()->user()->pregnancy_start->diffInDays(today(), false));
    $week = intdiv($days, 7);
    $stage = $week < 14 ? 1 : ($week < 28 ? 2 : 3);
@endphp
<div class="page-heading"><div><p class="eyebrow">NHẬT KÝ CỦA MẸ</p><h1>Chào {{ auth()->user()->name }} <span class="greeting-flower" aria-hidden="true">✿</span></h1><p>Một ngày mới, thêm một điều để lưu giữ.</p></div><a class="btn btn-primary" href="{{ route('entries.index', ['kind'=>'meals']) }}#write-diary">+ Viết nhật ký</a></div>
<section class="journey">
    <div><p class="eyebrow">HÀNH TRÌNH HIỆN TẠI</p><h2>Tuần {{ $week }}<span> + {{ $days % 7 }} ngày</span></h2><p>Tam cá nguyệt {{ $stage }} · Tính từ ngày đầu kỳ kinh cuối</p><a href="{{ route('profile') }}">Xem hồ sơ của mẹ <span aria-hidden="true">↗</span></a></div>
    <div class="journey-flower" aria-hidden="true">✺</div>
    <div class="journey-progress">
        <div class="progress-label"><span>Ngày bắt đầu {{ auth()->user()->pregnancy_start->format('d/m/Y') }}</span><span>{{ min(100, (int) ($days / 280 * 100)) }}%</span></div>
        <div class="progress" role="progressbar" aria-label="Hành trình so với mốc 40 tuần" aria-valuenow="{{ min(280, $days) }}" aria-valuemin="0" aria-valuemax="280"><div class="progress-bar" style="width:{{ min(100, $days / 280 * 100) }}%"></div></div>
        <div class="progress-label"><span>Khởi đầu</span><span>Mốc 40 tuần</span></div>
    </div>
</section>
<div class="stats-grid">
    <section class="stat"><span class="stat-icon sage" aria-hidden="true">✎</span><div><p>Bài nhật ký</p><strong>{{ $mealCount }}</strong><small>trong hành trình của mẹ</small></div></section>
    <section class="stat"><span class="stat-icon peach" aria-hidden="true">▤</span><div><p>Hồ sơ khám thai</p><strong>{{ $checkupCount }}</strong><small>lần khám đã lưu</small></div></section>
    <section class="stat"><span class="stat-icon lavender" aria-hidden="true">▦</span><div><p>Lịch hẹn sắp tới</p><strong class="stat-date">{{ $nextVisit ? $nextVisit->next_visit->format('d/m') : 'Chưa có' }}</strong><small>{{ $nextVisit ? $nextVisit->title : 'Thêm từ hồ sơ khám thai' }}</small></div></section>
</div>
<div class="dashboard-columns">
    <section class="panel">
        <div class="section-heading"><div><p class="eyebrow">NHỮNG ĐIỀU MUỐN NHỚ</p><h2>Nhật ký gần đây</h2></div><a href="{{ route('entries.index', ['kind'=>'meals']) }}">Xem dòng thời gian ↗</a></div>
        @forelse($meals as $meal)
        <a class="entry-preview" href="{{ route('entries.edit', ['kind'=>'meals', 'id'=>$meal->id]) }}"><span class="date-tile">{{ $meal->entry_date->format('d') }}<small>TH {{ $meal->entry_date->format('m') }}</small></span><div><span class="subtle-label">{{ $meal->entry_date->format('d/m/Y') }} · {{ count($meal->photos()) }} ảnh</span><h3>{{ $meal->title }}</h3><p>{{ Str::limit($meal->notes, 72) }}</p></div><span class="entry-arrow">↗</span></a>
        @empty
        <div class="empty-state"><span aria-hidden="true">✿</span><h3>Một trang nhật ký đang chờ bạn</h3><p>Viết vài dòng về hôm nay và lưu lại một tấm ảnh.</p><a href="{{ route('entries.index', ['kind'=>'meals']) }}#write-diary">Viết nhật ký đầu tiên ↗</a></div>
        @endforelse
    </section>
    <section class="panel">
        <div class="section-heading"><div><p class="eyebrow">THEO TỪNG GIAI ĐOẠN</p><h2>Hồ sơ khám thai</h2></div><a href="{{ route('entries.create', ['kind'=>'checkups']) }}" aria-label="Thêm hồ sơ khám thai">+</a></div>
        @forelse($checkups as $checkup)
        <a class="checkup-preview" href="{{ route('entries.edit', ['kind'=>'checkups', 'id'=>$checkup->id]) }}"><span class="record-dot" aria-hidden="true"></span><div><span class="subtle-label">{{ $checkup->entry_date->format('d/m/Y') }}</span><h3>{{ $checkup->title }}</h3><p>{{ $checkup->clinic ?: 'Chưa ghi cơ sở khám' }}</p></div><span class="entry-arrow">↗</span></a>
        @empty
        <div class="empty-state"><span aria-hidden="true">▤</span><h3>Lưu những lần gặp bé</h3><p>Giữ phiếu khám, ảnh siêu âm và lời dặn trong cùng một chỗ.</p><a href="{{ route('entries.create', ['kind'=>'checkups']) }}">Thêm hồ sơ đầu tiên ↗</a></div>
        @endforelse
    </section>
</div>
<p class="page-footnote">Mầm · Một góc nhỏ cho hành trình của mẹ.</p>
@endsection
