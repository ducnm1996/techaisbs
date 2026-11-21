@extends('layout')
@section('title','Đăng nhập')
@section('content')
<div class="auth-card"><span class="kicker">KHÔNG GIAN RIÊNG CỦA BẠN</span><h1>Chào bạn trở lại.</h1><p class="muted">Theo dõi lịch sử thiết bị trong một nơi an toàn.</p>
<form method="post" action="{{ route('login') }}">@csrf<label for="email">Email tài khoản</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="254"><label for="password">Mật khẩu</label><div class="password-field"><input class="form-control" id="password" type="password" name="password" required autocomplete="current-password" maxlength="200"><button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Hiện mật khẩu">Hiện</button></div><button class="btn btn-primary w-100 mt-4">Đăng nhập</button></form><a class="quiet-link" href="{{ route('password.request') }}">Thiết lập hoặc đặt lại mật khẩu</a><p class="small muted mt-3">Tài khoản do quản trị viên cấp. Không có đăng ký công khai.</p></div>
@endsection
