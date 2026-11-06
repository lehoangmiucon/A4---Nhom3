@extends('admin.layouts.app')

@section('title')
Địa điểm &amp; thiết bị · Helpdesk trường học
@endsection

@section('content')
                <section class="page-title-row locations-page-heading">
                    <div>
                        <p class="eyebrow">QUẢN LÝ CƠ SỞ VẬT CHẤT</p>
                        <h1>Địa điểm &amp; thiết bị</h1>
                        <p>Quản lý khu nhà và phòng học trong toàn trường.</p>
                    </div>
                    <div class="title-actions"><button class="button button-primary" type="button" data-open-location="building"><i class="fa-solid fa-plus" aria-hidden="true"></i> Thêm khu nhà</button></div>
                </section>
                <section class="summary-strip location-summary-strip" aria-label="Tổng quan địa điểm">
                    <div class="summary-tile"><span><i class="fa-solid fa-building" aria-hidden="true"></i> Khu nhà</span><strong id="building-count">{{ $buildings->count() }}</strong></div>
                    <div class="summary-tile"><span><i class="fa-solid fa-door-open" aria-hidden="true"></i> Phòng học &amp; không gian</span><strong id="room-count">{{ $roomCount }}</strong></div>
                </section>
                <section class="location-toolbar" aria-label="Tìm khu nhà">
                    <div class="location-search-control"><label class="location-search-box" for="building-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="building-search" type="search" aria-label="Tìm khu nhà" placeholder="Tìm theo tên khu nhà, mã hoặc địa chỉ…"></label><button class="location-search-clear" type="button" data-clear-building-search aria-label="Xóa nội dung tìm kiếm" hidden><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
                    <span class="location-results" id="building-results">{{ $buildings->count() }} khu nhà</span>
                </section>
                <div class="info-grid location-building-grid" id="building-grid">
                    @php($buildingColors = ['#1686DF', '#806CE0', '#159B83', '#D98B2B', '#D66A72', '#5273C7'])
                    @forelse($buildings as $buildingIndex => $buildingItem)
                    <article class="info-card location-building-card" style="--building-accent: {{ $buildingColors[$buildingIndex % count($buildingColors)] }}" data-building-card data-search="{{ $buildingItem->name }} {{ $buildingItem->code }} {{ $buildingItem->description }}">
                        <div class="location-building-heading">
                            <span class="building-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                            <div><span class="location-building-code">KHU NHÀ · {{ $buildingItem->code }}</span><h3>{{ $buildingItem->name }}</h3></div>
                        </div>
                        <p class="location-building-description">{{ $buildingItem->description ?: 'Chưa có mô tả cho khu nhà này.' }}</p>
                        <div class="location-building-stats"><span><i class="fa-solid fa-door-open" aria-hidden="true"></i>{{ $buildingItem->rooms_count }} phòng</span><span><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>{{ $buildingItem->rooms->sum(fn ($room) => $room->tickets->whereNotIn('status', ['closed'])->count()) }} phiếu đang mở</span></div>
                        <div class="location-building-footer"><span>Danh sách phòng và mã QR</span><a class="location-detail-link" href="building-detail?code={{ urlencode($buildingItem->code) }}">Xem chi tiết <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
                    </article>
                    @empty
                    <div class="location-empty-state"><span><i class="fa-solid fa-building-circle-exclamation" aria-hidden="true"></i></span><div><h2>Chưa có khu nhà</h2><p>Tạo khu nhà đầu tiên để bắt đầu thêm phòng và mã QR báo sự cố.</p><button class="button button-primary" type="button" data-open-location="building"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tạo khu nhà</button></div></div>
                    @endforelse
                    <div class="location-empty-state location-search-empty" data-building-search-empty hidden><span><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span><div><h2>Không tìm thấy khu nhà</h2><p>Thử tìm bằng tên hoặc mã khu nhà khác.</p><button class="button button-secondary" type="button" data-clear-building-search>Đặt lại tìm kiếm</button></div></div>
                </div>
                <dialog class="location-dialog" id="location-dialog" aria-labelledby="location-dialog-title">
                    <form id="location-form">
                        <header class="location-dialog-header"><span class="location-dialog-icon"><i class="fa-solid fa-building-circle-check" aria-hidden="true"></i></span><button class="icon-button" type="button" data-close-location aria-label="Đóng"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
                        <p class="eyebrow">DANH MỤC CƠ SỞ VẬT CHẤT</p>
                        <h2 id="location-dialog-title">Tạo khu nhà</h2>
                        <p class="section-lead">Khai báo khu nhà trước, sau đó tạo phòng và chọn khu nhà chứa phòng.</p>
                        <div class="location-mode-switch" role="tablist" aria-label="Loại địa điểm"><button type="button" class="active" role="tab" aria-selected="true" data-location-mode="building">Khu nhà</button><button type="button" role="tab" aria-selected="false" data-location-mode="room">Phòng / không gian</button></div>
                        <div class="form-grid location-form-grid">
                            <div class="form-item" data-building-field><label for="new-building-name">Tên khu nhà <b>*</b></label><input id="new-building-name" placeholder="Ví dụ: Khu giảng đường C" required></div>
                            <div class="form-item" data-building-field><label for="new-building-code">Mã khu nhà <b>*</b></label><input id="new-building-code" placeholder="Ví dụ: C" required></div>
                            <div class="form-item full" data-building-field><label for="new-building-address">Địa chỉ / cơ sở</label><input id="new-building-address" placeholder="Ví dụ: Cơ sở chính"></div>
                            <div class="form-item" data-room-field hidden><label for="new-room-building">Thuộc khu nhà <b>*</b></label><select id="new-room-building" required>
                                    <option value="">Chọn khu nhà</option>
                                    @foreach($buildings as $buildingOption)
                                    <option value="{{ $buildingOption->id }}" data-code="{{ $buildingOption->code }}">{{ $buildingOption->name }}</option>
                                    @endforeach
                                </select></div>
                            <div class="form-item" data-room-field hidden><label for="new-room-code">Mã phòng <b>*</b></label><input id="new-room-code" placeholder="Ví dụ: A.203" required></div>
                            <div class="form-item" data-room-field hidden><label for="new-room-name">Tên phòng / không gian <b>*</b></label><input id="new-room-name" placeholder="Ví dụ: Phòng học 203" required></div>
                            <div class="form-item" data-room-field hidden><label for="new-room-floor">Tầng</label><input id="new-room-floor" type="number" min="0" max="99" placeholder="Tự nhận từ mã phòng"></div>
                            <div class="form-item full room-floor-hint" data-room-field hidden><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Tầng tự điền theo mã phòng (ví dụ A201 → tầng 2). Bạn có thể chỉnh lại nếu cần.</span></div>
                            <div class="form-item full room-qr-preview" data-room-field hidden><span class="room-qr-preview-icon"><i class="fa-solid fa-qrcode" aria-hidden="true"></i></span>
                                <div><strong>Liên kết QR của phòng</strong><code id="room-qr-link">Chọn khu nhà và nhập mã phòng</code><small>Dùng liên kết này khi tạo mã QR dán tại phòng.</small></div>
                            </div>
                        </div>
                        <p id="location-feedback" class="api-feedback" role="status" aria-live="polite" hidden></p>
                        <footer class="location-dialog-actions"><button class="button button-secondary" type="button" data-close-location>Hủy</button><button class="button button-primary" type="submit" id="location-submit">Tạo khu nhà</button></footer>
                    </form>
                </dialog>
@endsection

@section('scripts')
<script src="../assets/js/locations.js?v=20261105-room-floor-input1"></script>
@endsection
