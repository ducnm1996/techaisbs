<section class="panel journal-composer" id="write-diary">
    <div class="section-heading">
        <div>
            <p class="eyebrow">MỘT TRANG CHO HÔM NAY</p>
            <h2>{{ $entry ? 'Chỉnh sửa nhật ký' : 'Viết nhật ký' }}</h2>
        </div>
        <span class="composer-flower" aria-hidden="true">✿</span>
    </div>
    <form method="post" enctype="multipart/form-data"
          action="{{ route($entry ? 'entries.update' : 'entries.store', ['kind' => 'meals', ...($entry ? ['id' => $entry->id] : [])]) }}">
        @csrf
        @if($entry) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="title">Tiêu đề</label>
                <input class="form-control" id="title" name="title" maxlength="160"
                       placeholder="Một điều bạn muốn nhớ hôm nay…"
                       value="{{ old('title', $entry?->title) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="entry_date">Ngày nhật ký</label>
                <input class="form-control" type="date" id="entry_date" name="entry_date"
                       min="1900-01-01"
                       max="{{ today()->toDateString() }}"
                       value="{{ old('entry_date', $entry?->entry_date?->toDateString() ?? today()->toDateString()) }}" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Nội dung</label>
                <textarea class="form-control journal-textarea" id="notes" name="notes" rows="4"
                          maxlength="10000" placeholder="Hôm nay của bạn thế nào?">{{ old('notes', $entry?->notes) }}</textarea>
            </div>
            @if($entry && count($entry->photos()))
                <div class="col-12">
                    <p class="form-label">Ảnh đã lưu</p>
                    <div class="journal-edit-photos">
                        @foreach($entry->photos() as $photo)
                            <label class="journal-edit-photo">
                                <img src="{{ route('journal.photo', ['id' => $entry->id, 'photo' => $photo]) }}"
                                     alt="Ảnh đã lưu trong nhật ký" width="240" height="180">
                                <span>
                                    <input class="form-check-input" type="checkbox" name="remove_photos[]"
                                           value="{{ $photo }}" @checked(in_array($photo, (array) old('remove_photos', []), true))>
                                    Gỡ ảnh này
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="col-12">
                <label class="form-label" for="photos">Ảnh đính kèm</label>
                <input class="form-control" type="file" id="photos" name="photos[]" multiple
                       accept=".jpg,.jpeg,.png,.webp" aria-describedby="photo-help" data-photo-input>
                <p class="form-text" id="photo-help">Tối đa 4 ảnh cho mỗi bài, 2 MB mỗi ảnh. Định dạng JPG, PNG hoặc WebP.</p>
                <div class="journal-photo-preview" data-photo-preview hidden aria-label="Ảnh sắp tải lên"></div>
            </div>
        </div>
        <div class="journal-compose-actions">
            @if($entry)
                <a class="btn btn-light" href="{{ route('dashboard') }}">Hủy</a>
            @endif
            <button class="btn btn-primary" type="submit">{{ $entry ? 'Lưu thay đổi' : 'Lưu nhật ký' }} <span aria-hidden="true">↗</span></button>
        </div>
    </form>
</section>
