@extends('admin.layouts.app')

@section('title', 'Chi tiết phòng · Helpdesk')
@section('content_class', 'room-detail-page')

@push('styles')
<link rel="stylesheet" href="{{ url('/admin/assets/css/room-print.css?v=20261103-print-qr-blue1') }}">
@endpush

@section('content')
@php
$reportUrl = url('/r/'.urlencode($room->qr_code ?: $room->code));
@endphp
<section class="page-title-row">
    <div>
        <p class="eyebrow">CHI TIẾT PHÒNG HỌC</p>
        <h1>{{ $room->name }}</h1>
        <p>{{ $room->building?->name }} · Mã phòng {{ $room->code }}</p>
    </div>
    <div class="title-actions">
        <a class="button button-secondary" href="building-detail?code={{ urlencode($room->building?->code ?? '') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Danh sách phòng</a>
        <button class="button button-primary" type="button" onclick="window.print()"><i class="fa-solid fa-print" aria-hidden="true"></i> In mã QR</button>
    </div>
</section>

<section class="room-detail-grid">
    <article class="panel room-detail-info">
        <p class="eyebrow">THÔNG TIN PHÒNG</p>
        <h2>{{ $room->name }}</h2>
        <dl>
            <div>
                <dt>Mã phòng</dt>
                <dd>{{ $room->code }}</dd>
            </div>
            <div>
                <dt>Khu nhà</dt>
                <dd>{{ $room->building?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt>Tầng</dt>
                <dd>{{ $room->floor ?? '—' }}</dd>
            </div>
            <div>
                <dt>Sự cố đang mở</dt>
                <dd>{{ $room->tickets->whereNotIn('status', ['closed'])->count() }}</dd>
            </div>
        </dl>
        <a class="button button-secondary" href="{{ $reportUrl }}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Mở biểu mẫu báo sự cố</a>
    </article>

    <article class="panel room-qr-card" id="room-qr-print-area">
        <div class="room-qr-print-brand">
            <img src="{{ url('/shared/images/helpdesk-logo.png') }}" alt="Helpdesk">
            <span>CỔNG HỖ TRỢ TRƯỜNG HỌC</span>
        </div>
        <p class="eyebrow">MÃ QR BÁO SỰ CỐ</p>
        <h2>Quét mã để báo sự cố tại phòng {{ $room->code }}</h2>
        <img id="room-qr-image" data-qr-value="{{ $reportUrl }}" alt="Mã QR báo sự cố tại phòng {{ $room->code }}" width="240" height="240">
        <p>{{ $room->building?->name }} · {{ $room->name }}</p>
        <p class="room-qr-scan-help">Dùng camera điện thoại để quét và gửi báo cáo sự cố.</p>
        <code>{{ $reportUrl }}</code>
        <button class="button button-primary room-qr-print-button" type="button" onclick="window.print()"><i class="fa-solid fa-print" aria-hidden="true"></i> In mã QR</button>
    </article>
</section>
@endsection

@section('scripts')
<script src="{{ url('/admin/assets/js/qrcode-generator.js?v=20261103-local1') }}"></script>
<script src="{{ url('/admin/assets/js/room-qr.js?v=20261103-local1') }}"></script>
@endsection
