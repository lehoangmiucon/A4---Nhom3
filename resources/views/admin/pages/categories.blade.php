@extends('admin.layouts.app')

@section('title')
Danh mục lỗi · Helpdesk trường học
@endsection

@section('content')
<section class="page-title-row">
                    <div>
                        <p class="eyebrow">PHÂN LOẠI SỰ CỐ</p>
                        <h1>Danh mục lỗi</h1>
                        <p>Mỗi danh mục có màu riêng để nhận biết nhanh trên lịch phân công.</p>
                    </div>
                </section>
                <p class="category-feedback" id="category-feedback" role="status" aria-live="polite" hidden></p>
                <div class="category-admin-grid">
                    <section class="panel table-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">DANH MỤC ĐANG DÙNG</p>
                                <h2 class="section-heading">Danh sách danh mục lỗi</h2>
                            </div><span class="device-tag">{{ $categories->count() }} danh mục</span>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>TÊN / MÀU LỊCH</th>
                                        <th>THAO TÁC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($categories as $category)
                                    <tr>
                                        <td>
                                            @php
                                                $categoryLabel = strtolower(\Illuminate\Support\Str::ascii($category->name));
                                                $categoryIcon = match (true) {
                                                    str_contains($categoryLabel, 'ban ghe'), str_contains($categoryLabel, 'noi that') => 'fa-chair',
                                                    str_contains($categoryLabel, 'lanh'), str_contains($categoryLabel, 'dieu hoa') => 'fa-snowflake',
                                                    str_contains($categoryLabel, 'chieu') => 'fa-video',
                                                    str_contains($categoryLabel, 'wifi'), str_contains($categoryLabel, 'mang') => 'fa-wifi',
                                                    str_contains($categoryLabel, 'dien'), str_contains($categoryLabel, 'sang'), str_contains($categoryLabel, 'den') => 'fa-lightbulb',
                                                    str_contains($categoryLabel, 'may tinh'), str_contains($categoryLabel, 'computer') => 'fa-computer',
                                                    default => 'fa-screwdriver-wrench',
                                                };
                                            @endphp
                                            <form class="category-edit-form" data-category-id="{{ $category->id }}"><span class="category-edit-icon" style="--category-color: {{ $category->color ?? '#3B82F6' }}" aria-hidden="true"><i class="fa-solid {{ $categoryIcon }}"></i></span><input type="color" class="category-color-input" name="color" value="{{ $category->color ?? '#3B82F6' }}" aria-label="Màu lịch cho {{ $category->name }}"><input class="category-edit-name" type="text" name="name" value="{{ $category->name }}" maxlength="100" required><button class="button button-secondary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Lưu</button></form>
                                        </td>
                                        <td><button class="button button-danger category-delete-button" type="button" data-category-delete="{{ $category->id }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Xóa</button></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="2">Chưa có danh mục lỗi. Hãy tạo danh mục đầu tiên.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="panel detail-panel">
                        <p class="eyebrow"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i> THÊM DANH MỤC</p>
                        <h2 class="section-heading">Danh mục mới</h2>
                        <form id="category-create-form" class="role-form category-create-form">
                            <label class="form-item"><span>Tên danh mục <b>*</b></span><span class="category-name-field"><i class="fa-solid fa-tag" aria-hidden="true"></i><input name="name" maxlength="100" required placeholder="Ví dụ: Âm thanh"></span></label>
                            <label class="form-item"><span>Màu hiển thị <b>*</b></span><span class="category-color-field"><input class="category-color-input" type="color" name="color" value="#3B82F6" required><span>Ch&#7885;n m&#224;u ph&#226;n bi&#7879;t</span></span></label>
                            <button class="button button-primary" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> T&#7841;o danh m&#7909;c</button>
                        </form>
                    </section>
                </div>
@endsection

@section('scripts')
<script src="../assets/js/categories.js"></script>
@endsection
