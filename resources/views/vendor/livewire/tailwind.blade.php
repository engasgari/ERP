@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';

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
                    <button
                        type="button"
                        class="erp-pagination__btn"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    >قبلی</button>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="erp-pagination__btn is-ellipsis">…</span></li>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        <li wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                            @if ((int) $page === (int) $paginator->currentPage())
                                <span class="erp-pagination__btn is-active" aria-current="page">{{ toPersianDigits((string) $page) }}</span>
                            @else
                                <button
                                    type="button"
                                    class="erp-pagination__btn"
                                    wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                >{{ toPersianDigits((string) $page) }}</button>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <button
                        type="button"
                        class="erp-pagination__btn"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    >بعدی</button>
                @else
                    <span class="erp-pagination__btn is-disabled" aria-disabled="true">بعدی</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
