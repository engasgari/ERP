@if ($paginator->hasPages())
    <nav class="erp-pagination" role="navigation" aria-label="صفحه‌بندی">
        <ul class="erp-pagination__list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="erp-pagination__btn is-disabled" aria-disabled="true">قبلی</span>
                @else
                    <a class="erp-pagination__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
                @endif
            </li>
            <li>
                @if ($paginator->hasMorePages())
                    <a class="erp-pagination__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>
                @else
                    <span class="erp-pagination__btn is-disabled" aria-disabled="true">بعدی</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
