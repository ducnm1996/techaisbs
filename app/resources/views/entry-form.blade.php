@extends('layout')
@section('title', $entry ? 'Chỉnh sửa nhật ký' : ($kind === 'meals' ? 'Ghi bữa ăn' : 'Thêm hồ sơ khám thai'))
@section('content')
<a class="back-link" href="{{ route('entries.index', ['kind'=>$kind]) }}">← Trở về nhật ký</a>
<div class="page-heading"><div><p class="eyebrow">{{ $kind === 'meals' ? 'ĂN UỐNG MỖI NGÀY' : 'MỘT LẦN GẶP BÉ' }}</p><h1>{{ $entry ? 'Chỉnh sửa nhật ký' : ($kind === 'meals' ? 'Hôm nay mẹ ăn gì?' : 'Lưu một lần khám thai') }}</h1><p>Ghi lại những điều bạn muốn nhớ.</p></div></div>
<form class="panel editor-form" method="post" enctype="multipart/form-data" action="{{ route($entry ? 'entries.update' : 'entries.store', ['kind'=>$kind, ...($entry ? ['id'=>$entry->id] : [])]) }}">
    @csrf @if($entry) @method('PUT') @endif
    <div class="row g-4">
        <div class="col-md-6"><label class="form-label" for="entry_date">Ngày {{ $kind === 'meals' ? 'ghi nhật ký' : 'khám' }} <span aria-hidden="true">*</span></label><input class="form-control" type="date" id="entry_date" name="entry_date" min="{{ auth()->user()->pregnancy_start->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ old('entry_date', $entry?->entry_date?->toDateString() ?? today()->toDateString()) }}" required></div>
        @if($kind === 'meals')
        <div class="col-md-6"><label class="form-label" for="meal_type">Bữa ăn <span aria-hidden="true">*</span></label><select class="form-select" id="meal_type" name="meal_type">@foreach(App\Models\Meal::TYPES as $value=>$label)<option value="{{ $value }}" @selected(old('meal_type', $entry?->meal_type) === $value)>{{ $label }}</option>@endforeach</select></div>
        @else
        <div class="col-md-6"><label class="form-label" for="clinic">Cơ sở khám / bác sĩ</label><input class="form-control" id="clinic" name="clinic" maxlength="160" value="{{ old('clinic', $entry?->clinic) }}"></div>
        @endif
        <div class="col-12"><label class="form-label" for="title">{{ $kind === 'meals' ? 'Món ăn / thực phẩm' : 'Tên lần khám' }} <span aria-hidden="true">*</span></label><input class="form-control" id="title" name="title" maxlength="160" placeholder="{{ $kind === 'meals' ? 'Ví dụ: Cháo yến mạch, trứng và một ly sữa' : 'Ví dụ: Khám thai định kỳ tuần 20' }}" value="{{ old('title', $entry?->title) }}" required></div>
        <div class="col-12"><label class="form-label" for="notes">{{ $kind === 'meals' ? 'Khẩu phần, cảm nhận và ghi chú' : 'Kết quả khám và lời dặn' }}</label><textarea class="form-control" id="notes" name="notes" rows="5" maxlength="10000" placeholder="Viết những điều bạn muốn lưu lại…">{{ old('notes', $entry?->notes) }}</textarea></div>
        @if($kind === 'checkups')
        <div class="col-md-6"><label class="form-label" for="next_visit">Ngày hẹn tiếp theo</label><input class="form-control" id="next_visit" name="next_visit" type="date" value="{{ old('next_visit', $entry?->next_visit?->toDateString()) }}"></div>
        <div class="col-12"><label class="form-label" for="attachment">Phiếu khám / ảnh siêu âm</label><input class="form-control" id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png"><p class="form-text">Một tệp PDF, JPG hoặc PNG, tối đa 10 MB. Tệp mới sẽ thay thế tệp hiện tại.</p>
        @if($entry?->file_path)
        <a class="attachment-link" href="{{ route('attachment', $entry->id) }}">↓ {{ $entry->file_name }}</a>
        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="remove_attachment" name="remove_attachment" value="1" @checked(old('remove_attachment'))><label class="form-check-label" for="remove_attachment">Gỡ tệp đính kèm hiện tại</label></div>
        @endif</div>
        @endif
    </div>
    <div class="form-actions"><a class="btn btn-light" href="{{ route('entries.index', ['kind'=>$kind]) }}">Hủy</a><button type="submit" class="btn btn-primary">Lưu vào nhật ký ↗</button></div>
</form>
@endsection
