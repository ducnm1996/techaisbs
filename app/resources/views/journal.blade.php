@extends('layout')
@section('title', 'Trang chủ')
@section('content')
<div class="journal-page">
<div class="page-heading">
    <div>
        <p class="eyebrow">HÀNH TRÌNH QUA TỪNG NGÀY</p>
        <h1>{{ config('app.name') }}</h1>
        <p>Ghi lại cảm xúc, kỷ niệm và những hình ảnh bạn muốn lưu giữ.</p>
    </div>
    <a class="btn btn-primary btn-sm" href="{{ route('entries.create', ['kind' => 'meals']) }}">+ Viết nhật ký</a>
</div>

<div class="journal-history-heading">
    <h2>Dòng thời gian</h2>
    <span>{{ $entries->total() }} bài nhật ký</span>
</div>
<details class="journal-filters" @if(request()->filled('stage') || request()->filled('date')) open @endif>
<summary class="btn btn-light btn-sm">Lọc</summary>
<form class="panel filter-bar" method="get">
    <div>
        <label class="form-label" for="stage">Giai đoạn</label>
        <select class="form-select" id="stage" name="stage">
            <option value="">Tất cả giai đoạn</option>
            <option value="before" @selected(request('stage') === 'before')>Trước mốc thai kỳ</option>
            <option value="1" @selected(request('stage') === '1')>Tam cá nguyệt 1 · tuần 0–13</option>
            <option value="2" @selected(request('stage') === '2')>Tam cá nguyệt 2 · tuần 14–27</option>
            <option value="3" @selected(request('stage') === '3')>Tam cá nguyệt 3 · từ tuần 28</option>
        </select>
    </div>
    <div>
        <label class="form-label" for="filter_date">Ngày nhật ký</label>
        <input class="form-control" type="date" id="filter_date" name="date" value="{{ request('date') }}">
    </div>
    <button class="btn btn-primary" type="submit">Lọc nhật ký</button>
    <a class="btn btn-light" href="{{ route('dashboard') }}">Bỏ lọc</a>
</form>
</details>

<div class="journal-timeline {{ $entries->count() ? 'has-entries' : '' }}">
    @forelse($entries->groupBy(fn ($entry) => $entry->entry_date->toDateString()) as $date => $dayEntries)
        <section class="timeline-day" aria-label="Nhật ký ngày {{ $dayEntries->first()->entry_date->format('d/m/Y') }}">
            <div class="timeline-date">
                <span class="timeline-dot" aria-hidden="true"></span>
                <time datetime="{{ $date }}">{{ $dayEntries->first()->entry_date->isToday() ? 'Hôm nay · ' : '' }}{{ $dayEntries->first()->entry_date->format('d/m/Y') }}</time>
            </div>
            @foreach($dayEntries as $entry)
                @php
                    $entryDays = (int) auth()->user()->pregnancy_start->diffInDays($entry->entry_date, false);
                    $entryWeek = $entryDays >= 0 ? intdiv($entryDays, 7) : null;
                    $photos = $entry->photos();
                @endphp
                <article class="panel timeline-card">
                    <div class="timeline-card-top">
                        <span class="subtle-label">{{ $entryWeek === null ? 'Trước mốc thai kỳ' : 'Tuần '.$entryWeek }} · {{ auth()->user()->name }}</span>
                        <a href="{{ route('entries.edit', ['kind' => 'meals', 'id' => $entry->id]) }}" class="timeline-edit">Chỉnh sửa</a>
                    </div>
                    <h3>{{ $entry->title }}</h3>
                    @if($entry->notes)<p class="entry-notes">{{ $entry->notes }}</p>@endif
                    @if($photos)
                        <div class="timeline-images {{ count($photos) === 1 ? 'single-photo' : '' }}">
                            @foreach($photos as $photo)
                                <a href="{{ route('journal.photo', ['id' => $entry->id, 'photo' => $photo]) }}" target="_blank" rel="noopener" aria-label="Mở ảnh đính kèm trong nhật ký {{ $entry->title }}">
                                    <img src="{{ route('journal.photo', ['id' => $entry->id, 'photo' => $photo]) }}"
                                         alt="Ảnh đính kèm trong nhật ký {{ $entry->title }}"
                                         width="960" height="720" loading="lazy">
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="timeline-card-footer">
                        <span>{{ $entry->created_at?->format('H:i') }} · {{ count($photos) }} ảnh</span>
                        <form method="post" action="{{ route('entries.destroy', ['kind' => 'meals', 'id' => $entry->id]) }}"
                              data-confirm="Xóa bài nhật ký và các ảnh đính kèm?">
                            @csrf @method('DELETE')
                            <button class="timeline-delete" type="submit">Xóa bài</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </section>
    @empty
        <div class="panel empty-state">
            <span aria-hidden="true">✿</span>
            <h2>{{ request()->filled('stage') || request()->filled('date') ? 'Chưa có bài nhật ký phù hợp' : 'Trang đầu tiên dành cho bạn' }}</h2>
            <p>{{ request()->filled('stage') || request()->filled('date') ? 'Thử chọn ngày hoặc giai đoạn khác.' : 'Bấm “Viết nhật ký” để lưu những dòng đầu tiên và ảnh kỷ niệm của bạn.' }}</p>
        </div>
    @endforelse
</div>
{{ $entries->links('pagination::bootstrap-5') }}
</div>
@endsection
