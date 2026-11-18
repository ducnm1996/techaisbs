@extends('layout')
@section('title', $kind === 'meals' ? 'Nhật ký thực phẩm' : 'Hồ sơ khám thai')
@section('content')
<div class="page-heading"><div><p class="eyebrow">LƯU LẠI TỪNG NGÀY</p><h1>{{ $kind === 'meals' ? 'Nhật ký thực phẩm' : 'Hồ sơ khám thai' }}</h1><p>{{ $kind === 'meals' ? 'Những món ăn và cảm nhận của mẹ.' : 'Các lần khám, giấy tờ và lời dặn của bác sĩ.' }}</p></div><a class="btn btn-primary" href="{{ route('entries.create', ['kind'=>$kind]) }}">+ {{ $kind === 'meals' ? 'Ghi bữa ăn' : 'Thêm hồ sơ' }}</a></div>
<form class="panel filter-bar" method="get">
    <div><label class="form-label" for="stage">Giai đoạn</label><select class="form-select" id="stage" name="stage"><option value="">Tất cả giai đoạn</option value="1" @selected(request('stage') === '1')>Tam cá nguyệt 1 · tuần 0–13</option><option value="2" @selected(request('stage') === '2')>Tam cá nguyệt 2 · tuần 14–27</option><option value="3" @selected(request('stage') === '3')>Tam cá nguyệt 3 · từ tuần 28</option></select></div>
    <div><label class="form-label" for="date">Ngày ghi</label><input class="form-control" type="date" id="date" name="date" value="{{ request('date') }}"></div>
    <button class="btn btn-primary" type="submit">Lọc nhật ký</button><a class="btn btn-light" href="{{ route('entries.index', ['kind'=>$kind]) }}">Bỏ lọc</a>
</form>
<div class="entries-list">
@forelse($entries as $entry)
@php
    $entryDays = (int) auth()->user()->pregnancy_start->diffInDays($entry->entry_date, false);
    $entryWeek = $entryDays >= 0 ? intdiv($entryDays, 7) : null;
    $entryStage = $entryWeek === null ? null : ($entryWeek < 14 ? 1 : ($entryWeek < 28 ? 2 : 3));
@endphp
<article class="panel diary-card">
    <div class="entry-top">
        <span class="subtle-label">{{ $entry->entry_date->format('d/m/Y') }} ·
            @if($entryWeek === null) Trước mốc thai kỳ @else Tuần {{ $entryWeek }} · Tam cá nguyệt {{ $entryStage }} @endif
        </span>
    </div>
    <h2>{{ $entry->title }}</h2>
    @if($kind === 'checkups' && $entry->clinic)<p class="text-muted">{{ $entry->clinic }}</p>@endif
    @if($entry->notes)<p class="entry-notes">{{ $entry->notes }}</p>@endif
    @if($kind === 'checkups' && $entry->next_visit)<p class="next-visit">Lịch hẹn tiếp theo · {{ $entry->next_visit->format('d/m/Y') }}</p>@endif
    <div class="entry-actions">
        @if($kind === 'checkups' && $entry->file_path)<a class="attachment-link" href="{{ route('attachment', $entry->id) }}">↓ {{ $entry->file_name }}</a>@endif
        <a class="btn btn-sm btn-light" href="{{ route('entries.edit', ['kind'=>$kind,'id'=>$entry->id]) }}">Chỉnh sửa</a>
        <form method="post" action="{{ route('entries.destroy', ['kind'=>$kind,'id'=>$entry->id]) }}" data-confirm="Xóa mục nhật ký này?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa</button></form>
    </div>
</article>
@empty
<div class="panel empty-state"><span aria-hidden="true">{{ $kind === 'meals' ? '♧' : '▤' }}</span><h2>{{ request()->hasAny(['stage','date']) ? 'Chưa có mục phù hợp' : 'Bắt đầu một trang nhật ký mới' }}</h2><p>{{ request()->hasAny(['stage','date']) ? 'Thử chọn giai đoạn hoặc ngày khác.' : 'Những điều mẹ ghi hôm nay sẽ luôn ở đây.' }}</p><a href="{{ route('entries.create', ['kind'=>$kind]) }}">Thêm mục đầu tiên ↗</a></div>
@endforelse
</div>
{{ $entries->links('pagination::bootstrap-5') }}
@endsection
