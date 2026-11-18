<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#214e43">
    <title>@yield('title', 'Góc nhỏ của mẹ') · Mầm</title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/mam.css?v={{ filemtime(public_path('assets/mam.css')) }}">
</head>
<body class="@guest auth-page @endguest">
<a class="visually-hidden-focusable skip-link" href="#main">Đến nội dung chính</a>
@auth
@php
    $days = max(0, (int) auth()->user()->pregnancy_start->diffInDays(today(), false));
    $week = intdiv($days, 7);
    $stage = $week < 14 ? 1 : ($week < 28 ? 2 : 3);
@endphp
<aside class="sidebar">
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">❧</span> mầm<span class="brand-dot">.</span></a>
    <p class="brand-caption">NHẬT KÝ THAI KỲ</p>
    <nav aria-label="Điều hướng chính">
        <a class="{{ request()->routeIs('dashboard') || request()->route('kind') === 'meals' ? 'active' : '' }}" href="{{ route('dashboard') }}"><span aria-hidden="true">◫</span> Trang chủ</a>
        <a class="{{ request()->route('kind') === 'checkups' ? 'active' : '' }}" href="{{ route('entries.index', ['kind'=>'checkups']) }}"><span aria-hidden="true">▤</span> Hồ sơ khám thai</a>
        <a class="{{ request()->routeIs('profile') ? 'active' : '' }}" href="{{ route('profile') }}"><span aria-hidden="true">◎</span> Hồ sơ của mẹ</a>
    </nav>
    <div class="sidebar-note"><span aria-hidden="true">✿</span><p>Mỗi ngày một chút,<br>lưu lại hành trình của mẹ.</p></div>
    <div class="sidebar-user">
        <span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
        <div><strong>{{ auth()->user()->name }}</strong><small>Nhật ký cá nhân</small></div>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="logout" aria-label="Đăng xuất" title="Đăng xuất">↗</button></form>
    </div>
</aside>
@endauth
<main id="main" class="@auth app-main @else auth-main @endauth">
    @auth<header class="topbar"><span>Khoảng trời riêng của mẹ</span><span>{{ today()->format('d.m.Y') }}</span></header>@endauth
    <div class="@auth page-content @endauth">
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Bạn kiểm tra lại giúp mình nhé.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </div>
</main>
<script src="/assets/mam.js?v={{ filemtime(public_path('assets/mam.js')) }}" defer></script>
</body>
</html>
