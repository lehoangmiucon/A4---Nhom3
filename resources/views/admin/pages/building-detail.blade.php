@extends('admin.layouts.app')

@section('title')
Chi tiết khu nhà · Helpdesk trường học
@endsection

@section('content_class', 'building-detail-page')

@section('content')
<section class="page-title-row">
                    <div>
                        <p class="eyebrow">CHI TIẾT CƠ SỞ VẬT CHẤT</p>
                        <h1 id="building-name">{{ $building->name }}</h1>
                        <p id="building-subtitle">{{ $building->code }} · {{ $building->description }}</p>
                    </div>
                    <div class="title-actions"><a class="button button-secondary" href="locations"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Danh sách khu nhà</a><a class="button button-primary" id="add-building-room" href="locations?create=room&amp;building={{ urlencode($building->code) }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tạo phòng</a></div>
                </section>
                <section class="summary-strip building-detail-stats">
                    <div class="summary-tile"><span>Phòng &amp; không gian</span><strong id="detail-room-count">{{ $building->rooms->count() }}</strong></div>
                    <div class="summary-tile"><span>Thiết bị đã ghi nhận</span><strong id="detail-device-count">{{ $building->rooms->sum(fn ($room) => $room->equipment->count()) }}</strong></div>
                    <div class="summary-tile"><span>Sự cố đang mở</span><strong id="detail-incident-count">{{ $building->rooms->sum(fn ($room) => $room->tickets->whereNotIn("status", ["closed"])->count()) }}</strong></div>
                    <div class="summary-tile"><span>Số tầng</span><strong id="detail-floor-count">{{ $building->rooms->pluck("floor")->filter()->unique()->count() }}</strong></div>
                </section>
                <section class="panel building-overview">
                    <div class="building-overview-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></div>
                    <div class="building-overview-copy">
                        <p class="eyebrow">THÔNG TIN KHU NHÀ</p>
                        <h2 id="detail-building-name">{{ $building->name }}</h2>
                        <p id="detail-building-description">{{ $building->description ?: "Chưa có mô tả." }}</p>
                    </div>
                    <div class="building-overview-facts">
                        <div><span>Mã khu nhà</span><strong id="detail-building-code">{{ $building->code }}</strong></div>
                        <div><span>Mô tả</span><strong id="detail-building-address">{{ $building->description ?: "—" }}</strong></div>

                    </div>
                    <div class="building-overview-actions"><button class="button button-secondary" type="button" data-edit-building><i class="fa-solid fa-pen" aria-hidden="true"></i> Sửa khu nhà</button><button class="button button-danger" type="button" data-delete-building data-building-id="{{ $building->id }}" data-building-name="{{ $building->name }}"><i class="fa-solid fa-trash" aria-hidden="true"></i> Xóa khu nhà</button></div>
                </section>
                <p class="api-feedback" id="building-detail-feedback" role="status" aria-live="polite" hidden></p>
                <section class="panel building-room-panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">DANH MỤC KHÔNG GIAN</p>
                            <h2 class="section-heading">Danh sách phòng</h2>
                            <p class="section-lead">Mỗi phòng được gắn với khu nhà này và có đường dẫn riêng để tạo mã QR báo sự cố.</p>
                        </div><span class="device-tag" id="room-total-badge">{{ $building->rooms->count() }} phòng</span>
                    </div>
                    <div class="filter-bar building-room-filter"><label class="search-field-wrap"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input class="search-field" id="room-search" type="search" placeholder="Tìm mã phòng hoặc tên phòng…"></label><select class="select-field" id="room-floor-filter" aria-label="Lọc theo tầng">
                            <option value="">Tất cả tầng</option>
                            @foreach($building->rooms->pluck('floor')->filter()->unique()->sort() as $floor)
                            <option value="{{ $floor }}">Tầng {{ $floor }}</option>
                            @endforeach
                        </select></div>
                    <div class="table-wrap">
                        <table class="room-table">
                            <thead>
                                <tr>
                                    <th>MÃ PHÒNG</th>
                                    <th>TÊN PHÒNG</th>
                                    <th>TẦNG</th>
                                    <th>THIẾT BỊ</th>
                                    <th>SỰ CỐ MỞ</th>
                                    <th>CHI TIẾT / MÃ QR</th>
                                </tr>
                            </thead>
                            <tbody id="building-room-list">
                                @forelse($building->rooms as $room)
                                <tr>
                                    <td><strong>{{ $room->code }}</strong></td>
                                    <td>{{ $room->name }}</td>
                                    <td>{{ $room->floor ?? "—" }}</td>
                                    <td>{{ $room->equipment->count() }}</td>
                                    <td>{{ $room->tickets->whereNotIn('status', ['closed'])->count() }}</td>
                                    <td><a class="button button-secondary room-detail-link" href="room-detail?room={{ $room->id }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> Xem chi tiết</a></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6">Khu nhà chưa có phòng.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="room-empty-state" id="room-empty-state" hidden><i class="fa-solid fa-door-open" aria-hidden="true"></i><strong>Không tìm thấy phòng phù hợp</strong><span>Thử thay đổi từ khóa hoặc tầng đang lọc.</span></div>
                </section>
                <dialog class="detail-edit-dialog" id="building-edit-dialog" aria-labelledby="building-edit-title">
                    <form id="building-edit-form">
                        <header><span class="detail-dialog-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span><button class="icon-button" type="button" data-close-building-dialog aria-label="Đóng"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
                        <p class="eyebrow">CẬP NHẬT THÔNG TIN</p>
                        <h2 id="building-edit-title">Sửa thông tin khu nhà</h2>
                        <div class="form-grid">
                            <div class="form-item full"><label for="edit-building-name">Tên khu nhà</label><input id="edit-building-name" required></div>
                            <div class="form-item full"><label for="edit-building-address">Địa chỉ</label><input id="edit-building-address"></div>
                            <div class="form-item full"><label for="edit-building-description">Mô tả</label><textarea id="edit-building-description" rows="3"></textarea></div>
                        </div>
                        <footer><button class="button button-secondary" type="button" data-close-building-dialog>Hủy</button><button class="button button-primary" type="submit">Lưu thay đổi</button></footer>
                    </form>
                </dialog>
                <dialog class="detail-edit-dialog" id="room-edit-dialog" aria-labelledby="room-edit-title">
                    <form id="room-edit-form">
                        <header><span class="detail-dialog-icon"><i class="fa-solid fa-door-open" aria-hidden="true"></i></span><button class="icon-button" type="button" data-close-room-dialog aria-label="Đóng"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
                        <p class="eyebrow">CẬP NHẬT DANH MỤC</p>
                        <h2 id="room-edit-title">Sửa thông tin phòng</h2>
                        <div class="form-grid">
                            <div class="form-item"><label for="edit-room-code">Mã phòng</label><input id="edit-room-code" readonly></div>
                            <div class="form-item"><label for="edit-room-floor">Tầng</label><input id="edit-room-floor" type="number" min="0" required></div>
                            <div class="form-item full"><label for="edit-room-name">Tên phòng</label><input id="edit-room-name" required></div>
                            <div class="form-item"><label for="edit-room-devices">Số thiết bị</label><input id="edit-room-devices" type="number" min="0" required></div>
                            <div class="form-item"><label for="edit-room-incidents">Sự cố đang mở</label><input id="edit-room-incidents" type="number" min="0" required></div>
                        </div>
                        <section class="edit-room-qr-panel"><img id="edit-room-qr-image" alt="Mã QR mở cổng báo sự cố cho phòng này">
                            <div>
                                <p class="eyebrow">MÃ QR BÁO SỰ CỐ</p><strong id="edit-room-qr-title">Mã QR phòng</strong>
                                <p>Quét mã để mở biểu mẫu với vị trí phòng đã điền sẵn.</p><a class="text-link" id="edit-room-qr-link" href="#" target="_blank" rel="noopener">Mở cổng báo sự cố ↗</a>
                                <div class="room-qr-actions"><button class="button button-secondary" type="button" data-print-room-qr><i class="fa-solid fa-print" aria-hidden="true"></i> In mã QR</button><button class="button button-primary" type="button" data-download-room-qr><i class="fa-solid fa-download" aria-hidden="true"></i> Tải mã QR</button></div><span class="room-qr-feedback" id="room-qr-feedback" role="status" aria-live="polite"></span>
                            </div>
                        </section>
                        <footer><button class="button button-secondary" type="button" data-close-room-dialog>Hủy</button><button class="button button-primary" type="submit">Lưu thay đổi</button></footer>
                    </form>
                </dialog>
@endsection

@section('scripts')
<script src="../assets/js/building-detail.js?v=20261103-delete-building1"></script>
@endsection
