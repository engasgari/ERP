@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

@if ($paginator->hasPages())
    <nav class="erp-pagination" role="navigation" aria-label="صفحه‌بندی">
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
                        wire:loading.attr="disabled"
                    >قبلی</button>
                @endif
            </li>
            <li>
                @if ($paginator->hasMorePages())
                    <button
                        type="button"
                        class="erp-pagination__btn"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                    >بعدی</button>
                @else
                    <span class="erp-pagination__btn is-disabled" aria-disabled="true">بعدی</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
