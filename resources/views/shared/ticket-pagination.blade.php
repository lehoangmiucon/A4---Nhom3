@if($paginator->hasPages())
    <nav class="ticket-pagination" aria-label="Phân trang phiếu">
        <span>Hiển thị {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} phiếu</span>
        <div class="ticket-pagination-links">
            @if($paginator->onFirstPage())
                <span aria-disabled="true">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước">‹</a>
            @endif
            @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if($page === $paginator->currentPage())
                    <span class="current" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" aria-label="Trang {{ $page }}">{{ $page }}</a>
                @endif
            @endforeach
            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau">›</a>
            @else
                <span aria-disabled="true">›</span>
            @endif
        </div>
    </nav>
@elseif($paginator->total() > 0)
    <div class="ticket-pagination"><span>Hiển thị {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} phiếu</span></div>
@endif
