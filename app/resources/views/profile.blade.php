@extends('layout')
@section('title', 'Hồ sơ của mẹ')
@section('content')
<div class="page-heading"><div><p class="eyebrow">THÔNG TIN CÁ NHÂN</p><h1>Hồ sơ của mẹ</h1><p>Ngày bắt đầu giúp Mầm sắp xếp các mục theo giai đoạn.</p></div></div>
<form class="panel editor-form" method="post" action="{{ route('profile') }}">
    @csrf @method('PUT')
    <label class="form-label" for="name">Tên của bạn</label><input class="form-control mb-4" id="name" name="name" maxlength="80" value="{{ old('name', auth()->user()->name) }}" required>
    <label class="form-label" for="email">Email đăng nhập</label><input class="form-control mb-4" id="email" type="email" value="{{ auth()->user()->email }}" readonly>
    <label class="form-label" for="pregnancy_start">Ngày đầu kỳ kinh cuối</label><input class="form-control" id="pregnancy_start" name="pregnancy_start" type="date" max="{{ today()->toDateString() }}" value="{{ old('pregnancy_start', auth()->user()->pregnancy_start->toDateString()) }}" required>
    <p class="form-text">Khi sửa ngày này, tuần và giai đoạn của các mục nhật ký sẽ được tính lại.</p>
    <div class="form-actions"><button type="submit" class="btn btn-primary">Lưu hồ sơ ↗</button></div>
</form>
@endsection
