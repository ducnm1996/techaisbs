@extends('layout')
@section('title', 'Trang chủ')
@section('content')
<div class="home-stage" data-home-stage>
    <div class="home-canvas" data-home-canvas>
        <div class="page-heading">
            <div><p class="eyebrow">HÀNH TRÌNH LỚN LÊN</p><h1>{{ config('app.name') }}</h1></div>
            <a class="btn btn-light btn-sm" href="{{ route('entries.index', ['kind' => 'meals']) }}">Xem nhật ký ↗</a>
        </div>
        @include('baby-growth')
    </div>
</div>
@endsection
@push('scripts')
<script src="/assets/home-fit.js?v={{ filemtime(public_path('assets/home-fit.js')) }}" defer></script>
@endpush
