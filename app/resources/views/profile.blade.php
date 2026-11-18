@extends('layout')
@section('title', 'Hồ sơ của mẹ')
@section('content')
<div class="page-heading"><div><p class="eyebrow">THÔNG TIN CÁ NHÂN</p><h1>Hồ sơ của mẹ</h1><p>Cập nhật thông tin của mẹ và tài khoản đăng nhập.</p></div></div>
<form class="panel editor-form" method="post" action="{{ route('profile') }}">
    @csrf @method('PUT')
    <label class="form-label" for="name">Tên hiển thị</label>
    <input class="form-control mb-4" id="name" name="name" autocomplete="name" maxlength="80" value="{{ old('name', auth()->user()->name) }}" required>
    <label class="form-label" for="email">Email đăng nhập (user)</label>
    <input class="form-control mb-4" id="email" name="email" type="email" autocomplete="username" maxlength="254" value="{{ old('email', auth()->user()->email) }}" required>
    <label class="form-label" for="pregnancy_start">Ngày đầu kỳ kinh cuối</label>
    <input class="form-control" id="pregnancy_start" name="pregnancy_start" type="date" min="1900-01-01" max="{{ today()->toDateString() }}" value="{{ old('pregnancy_start', auth()->user()->pregnancy_start->toDateString()) }}" required>
    <p class="form-text mb-4">Khi sửa ngày này, tuần và giai đoạn của các mục nhật ký sẽ được tính lại.</p>
    <h2 class="h5 mt-4 mb-3">Thông tin xác thực</h2>
    <label class="form-label" for="password">Mật khẩu mới (tùy chọn)</label>
    <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="200">
    <p class="form-text mb-3">Mật khẩu mới cần ít nhất 12 ký tự.</p>
    <label class="form-label" for="password_confirmation">Nhập lại mật khẩu mới</label>
    <input class="form-control mb-4" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="200">
    <label class="form-label" for="current_password">Mật khẩu hiện tại</label>
    <input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" maxlength="200" aria-describedby="current-password-help">
    <p class="form-text" id="current-password-help">Cần nhập khi đổi email đăng nhập hoặc mật khẩu.</p>
    <div class="form-actions"><button type="submit" class="btn btn-primary">Lưu hồ sơ ↗</button></div>
</form>
@endsection
