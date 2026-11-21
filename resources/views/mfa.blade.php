@extends('layout')
@section('title','Xác thực hai lớp')
@section('content')
<div class="auth-card"><span class="kicker">BẢO VỆ TÀI KHOẢN QUẢN TRỊ</span><h1>Xác thực hai lớp</h1>@if($secret)<p>Thêm tài khoản TOTP vào ứng dụng xác thực bằng khóa dưới đây (6 chữ số, chu kỳ 30 giây). Sau đó nhập mã để hoàn tất kích hoạt.</p><code class="mfa-secret">{{ $secret }}</code><p class="small muted">Lưu khóa trong trình quản lý mật khẩu an toàn. Không chia sẻ khóa. Tài khoản quản trị chưa được vào portal trước khi xác nhận.</p>@else<p>Nhập mã hiện tại từ ứng dụng xác thực của bạn.</p>@endif<form method="post" action="{{ route('mfa.confirm') }}">@csrf<label for="code">Mã xác thực</label><input class="form-control" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required><button class="btn btn-primary w-100 mt-4">Xác nhận</button></form></div>
@endsection
