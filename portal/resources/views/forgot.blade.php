@extends('layout')
@section('title','Yêu cầu liên kết')
@section('content')
<div class="auth-card"><span class="kicker">KHÔI PHỤC TRUY CẬP</span><h1>Nhận liên kết mật khẩu</h1><p class="muted">Áp dụng cho tài khoản đã được quản trị viên cấp.</p><form method="post" action="{{ route('password.email') }}">@csrf<label for="email">Email tài khoản</label><input class="form-control" type="email" name="email" id="email" required autocomplete="username" maxlength="254"><button class="btn btn-primary w-100 mt-4">Gửi liên kết</button></form><a class="quiet-link" href="{{ route('login') }}">Về đăng nhập</a></div>
@endsection
