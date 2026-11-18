@extends('layout')
@section('title', $register ? 'Tạo nhật ký của bạn' : 'Đăng nhập')
@section('content')
<div class="auth-shell">
    <section class="auth-story">
        <a class="brand" href="{{ route('login') }}"><span class="brand-mark">❧</span> mầm<span class="brand-dot">.</span></a>
        <div class="auth-message"><p class="eyebrow">DÀNH CHO HÀNH TRÌNH CỦA MẸ</p><h1>Từng ngày nhỏ.<br>Một hành trình<br><em>thật lớn.</em></h1><p>Những bữa ăn, lần khám và ghi chú thân thương.<br>Gom lại trong một cuốn nhật ký của riêng bạn.</p></div>
        <div class="botanical" aria-hidden="true"><div class="stem"></div><i></i><i></i><i></i><i></i><i></i></div>
        <p class="auth-foot">Cùng mẹ lưu giữ những điều đáng nhớ.</p>
    </section>
    <section class="auth-form">
        <p class="eyebrow">NHẬT KÝ THAI KỲ CÁ NHÂN</p>
        <h2>{{ $register ? 'Bắt đầu cùng Mầm' : 'Mừng mẹ trở lại' }}<span class="accent">.</span></h2>
        <p class="text-muted mb-4">{{ $register ? 'Tạo tài khoản để lưu hành trình của riêng bạn.' : 'Đăng nhập để mở cuốn nhật ký của bạn.' }}</p>
        <form method="post" action="{{ route($register ? 'register' : 'login') }}">
            @csrf
            @if($register)
            <label class="form-label" for="name">Tên của bạn</label>
            <input class="form-control mb-3" id="name" name="name" autocomplete="name" maxlength="80" value="{{ old('name') }}" required>
            @endif
            <label class="form-label" for="email">Email</label>
            <input class="form-control mb-3" type="email" id="email" name="email" autocomplete="email" value="{{ old('email') }}" maxlength="254" required>
            <label class="form-label" for="password">Mật khẩu{{ $register ? ' · tối thiểu 12 ký tự' : '' }}</label>
            <input class="form-control mb-3" type="password" id="password" name="password" autocomplete="{{ $register ? 'new-password' : 'current-password' }}" @if($register) minlength="12" @endif maxlength="200" required>
            @if($register)
            <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label>
            <input class="form-control mb-3" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="200" required>
            <label class="form-label" for="pregnancy_start">Ngày đầu kỳ kinh cuối</label>
            <input class="form-control" type="date" id="pregnancy_start" name="pregnancy_start" max="{{ today()->toDateString() }}" value="{{ old('pregnancy_start') }}" required>
            <p class="form-text mb-3">Dùng để sắp xếp nhật ký theo tuần và tam cá nguyệt. Bạn có thể sửa trong hồ sơ.</p>
            @endif
            <button class="btn btn-primary w-100 mt-2" type="submit">{{ $register ? 'Tạo nhật ký của tôi' : 'Mở nhật ký' }} <span aria-hidden="true">↗</span></button>
        </form>
        <p class="auth-switch">{{ $register ? 'Đã có tài khoản?' : 'Lần đầu ghé Mầm?' }} <a href="{{ route($register ? 'login' : 'register') }}">{{ $register ? 'Đăng nhập' : 'Tạo nhật ký mới' }}</a></p>
        <p class="privacy-note">Hồ sơ và tệp đính kèm chỉ hiển thị khi bạn đăng nhập đúng tài khoản.</p>
    </section>
</div>
@endsection
