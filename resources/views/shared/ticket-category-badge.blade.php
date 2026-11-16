@php
    $badgeColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $category?->color)
        ? $category->color
        : '#64748B';
@endphp
<span class="ticket-category-badge" style="--ticket-category-color: {{ $badgeColor }}">{{ $label ?: 'Chưa phân loại' }}</span>
