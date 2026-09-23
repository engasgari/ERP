<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

class PersianPdf
{
    public static function fontPath(string $filename): string
    {
        return str_replace('\\', '/', storage_path('fonts/' . $filename));
    }

    public static function fontStylesPartialData(): array
    {
        return [
            'forPdf' => true,
            'pdfFontRegular' => self::fontPath('Vazirmatn-Regular.ttf'),
            'pdfFontBold' => self::fontPath('Vazirmatn-Bold.ttf'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function loadView(string $view, array $data = [], string $paper = 'a4', string $orientation = 'portrait'): PdfDocument
    {
        $pdf = Pdf::loadView($view, array_merge($data, ['forPdf' => true], self::fontStylesPartialData()))
            ->setPaper($paper, $orientation);

        self::applyOptions($pdf);

        return $pdf;
    }

    public static function make(string $html, string $paper = 'a4', string $orientation = 'portrait'): PdfDocument
    {
        $pdf = Pdf::loadHTML($html)->setPaper($paper, $orientation);
        self::applyOptions($pdf);

        return $pdf;
    }

    private static function applyOptions(PdfDocument $pdf): void
    {
        $pdf->setOption('defaultFont', 'vazirmatn');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('dpi', 96);
        $pdf->setOption('enable_font_subsetting', true);
        $pdf->setOption('defaultMediaType', 'print');
    }
}
