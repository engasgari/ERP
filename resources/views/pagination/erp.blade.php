@php
    $paginator->onEachSide(1);
    $window = \Illuminate\Pagination\UrlWindow::make($paginator);
    $elements = array_filter([
        $window['first'],
        is_array($window['slider']) ? '…' : null,
        $window['slider'],
        is_array($window['last']) ? '…' : null,
        $window['last'],
    ]);
@endphp

@if ($paginator->hasPages())
    <nav class="erp-pagination" role="navigation" aria-label="صفحه‌بندی">
        <div class="erp-pagination__summary">
            {{ toPersianDigits((string) $paginator->firstItem()) }}
            –
            {{ toPersianDigits((string) $paginator->lastItem()) }}
            از
            {{ toPersianDigits((string) $paginator->total()) }}
        </div>

        <ul class="erp-pagination__list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="erp-pagination__btn is-disabled" aria-disabled="true">قبلی</span>
                @else
                    <a class="erp-pagination__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="erp-pagination__btn is-ellipsis">…</span></li>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ((int) $page === (int) $paginator->currentPage())
                                <span class="erp-pagination__btn is-active" aria-current="page">{{ toPersianDigits((string) $page) }}</span>
                            @else
                                <a class="erp-pagination__btn" href="{{ $url }}">{{ toPersianDigits((string) $page) }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

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
